<?php

namespace People;

use People\DTO\LaneData;
use People\Event\LaneAdded;
use People\Event\LaneDeleted;
use People\Event\LaneUpdated;
use People\Event\OrganizationMemberRemoved;
use People\Event\OrganizationMemberActivationChanged;
use Rhumsaa\Uuid\Uuid;
use Application\Entity\User;
use Application\DomainEntity;
use Application\DuplicatedDomainEntityException;
use Application\DomainEntityUnavailableException;
use Application\InvalidArgumentException;
use Accounting\Account;

class Organization extends DomainEntity
{
	CONST ROLE_MEMBER = 'member';
	CONST ROLE_ADMIN  = 'admin';
	CONST ROLE_CONTRIBUTOR  = 'contributor';

	CONST KANBANIZE_SETTINGS = 'kanbanize';

	CONST ORG_SETTINGS = 'orgsettings';

	CONST MIN_KANBANIZE_COLUMN_NUMBER = 7;

	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var Uuid
	 */
	private $accountId;
	/**
	 * @var array
	 */
	private $members = [];
	/**
	 * @var \DateTime
	 */
	private $createdAt;
	/**
	 * @var array
	 */
	private $settings = [];

	private $lanes = [];

	private $syncErrorsNotification = false;

	public static function create($name, User $createdBy) {
		$rv = new self();
		$rv->recordThat(OrganizationCreated::occur(Uuid::uuid4()->toString(), array(
			'by' => $createdBy->getId(),
		)));
		$rv->setName($name, $createdBy);
		$rv->addMember($createdBy, self::ROLE_ADMIN);
		return $rv;
	}

	public function setName($name, User $updatedBy) {
		$s = is_null($name) ? null : trim($name);
		$this->recordThat(OrganizationUpdated::occur($this->id->toString(), array(
			'name' => $s,
			'by' => $updatedBy->getId(),
		)));
		return $this;
	}

	public function setSettings($settingKey, $settingValue, User $updatedBy){
		if(is_null($settingKey)){
			throw new InvalidArgumentException('Cannot address setting without a setting key');
		}

		$this->recordThat(OrganizationUpdated::occur($this->id->toString(), array(
			'settingKey' => trim($settingKey),
			'settingValue' => $settingValue,
			'by' => $updatedBy->getId(),
		)));

		return $this;
	}

	public function getParams() {

		$settings = $this->getSettings(self::ORG_SETTINGS);

		if ($settings) {
			return $settings;
		}

		return ValueObject\OrganizationParams::createWithDefaults();
	}

	public function setParams($data, User $updatedBy) {

		$settings = ValueObject\OrganizationParams::fromArray($data);
		$this->setSettings(self::ORG_SETTINGS, $settings, $updatedBy);

		return $this;
	}

	public function getSettings($key = null) {
		if(is_null($key)){
			return $this->settings;
		}
		if(array_key_exists($key, $this->settings)){
			return $this->settings[$key];
		}
		return null;
	}

    public function setLanes($lanes, User $updatedBy) {
        $lanes = is_null($lanes)||!is_array($lanes) ? [] : $lanes;
        $this->recordThat(OrganizationUpdated::occur($this->id->toString(), array(
            'lanes' => $lanes,
            'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function addLane(Uuid $id, LaneData $dto, User $by)
    {
	    $e = LaneAdded::happened($this->id->toString(), $id, $dto->name, $by);

        $this->recordThat($e);
    }

    public function updateLane($id, LaneData $dto, User $by)
    {
        if (!array_key_exists($id, $this->lanes)) {
            throw new InvalidArgumentException("lane with id $id does not exists");
        }

        $e = LaneUpdated::happened($this->id->toString(), Uuid::fromString($id), $dto->name, $by);

        $this->recordThat($e);
    }

    public function deleteLane($id, User $by)
    {
        if (!array_key_exists($id, $this->lanes)) {
            throw new InvalidArgumentException("lane with id $id does not exists");
        }

        $e = LaneDeleted::happened($this->id->toString(), Uuid::fromString($id), $by);

        $this->recordThat($e);
    }

    public function getName() {
		return $this->name;
	}

	public function changeAccount(Account $account, User $updatedBy) {
		$payload = array(
				'accountId' => $account->getId(),
				'by' => $updatedBy->getId(),
		);
		if(!is_null($this->accountId)) {
			$payload['prevAccountId'] = $this->accountId->toString();
		}
		$this->recordThat(OrganizationAccountChanged::occur($this->id->toString(), $payload));
		return $this;
	}

	public function getAccountId() {
		return $this->accountId;
	}

	public function addMember(User $user, $role = self::ROLE_CONTRIBUTOR, User $addedBy = null) {
		if (array_key_exists($user->getId(), $this->members)) {
			throw new DuplicatedDomainEntityException($this, $user);
		}
		$this->recordThat(OrganizationMemberAdded::occur($this->id->toString(), array(
			'userId' => $user->getId(),
			'role' => $role,
			'active' => true,
			'by' => $addedBy == null ? $user->getId() : $addedBy->getId(),
		)));
	}

	public function changeMemberRole(User $member, $role, User $changedBy = null) {
		if (!array_key_exists($member->getId(), $this->members)) {
			throw new DomainEntityUnavailableException($this, $member);
		}

		$this->recordThat(OrganizationMemberRoleChanged::occur($this->id->toString(), array(
			'userId' => $member->getId(),
			'organizationId' => $this->getId(),
			'newRole' => $role,
			'oldRole' => $this->members[$member->getId()]['role'],
			'by' => $changedBy == null ? $member->getId() : $changedBy->getId(),
		)));
	}

    public function changeMemberActivation(User $member, $active, User $changedBy = null)
    {
        if (!array_key_exists($member->getId(), $this->members)) {
            throw new DomainEntityUnavailableException($this, $member);
        }
        $memberId = Uuid::fromString($member->getId());
        $orgId = Uuid::fromString($this->getId());
        $e = OrganizationMemberActivationChanged::happened($this->id->toString(), $orgId, $member, $active, $changedBy);

        $this->recordThat($e);
    }

    public function removeMember(User $member, User $removedBy = null)
	{
		if (!array_key_exists($member->getId(), $this->members)) {
			throw new DomainEntityUnavailableException($this, $member);
		}

		$by = $removedBy == null ? $member : $removedBy;
		$uuid = Uuid::fromString($member->getId());
        $e = OrganizationMemberRemoved::happened($this->id->toString(), $uuid, $by);

        $this->recordThat($e);
	}
	
    public function hasSyncErrorsNotificationSet()
    {
        return $this->syncErrorsNotification;
    }
		
	public function clearSyncErrorsNotification(User $by)
    {
        $this->recordThat(OrganizationUpdated::occur($this->id->toString(), array(
            'syncErrorsNotification' => false,
            'by' => $by->getId(),
        )));
    }
	
    public function setSyncErrorsNotification(User $by)
    {   
        $this->recordThat(OrganizationUpdated::occur($this->id->toString(), array(
            'syncErrorsNotification' => true,
            'by' => $by->getId(),
        )));
    }

	public function shiftOutWarning(User $member, $minCredits, $minItems, $withinDays, User $by)
    {
        $this->recordThat(ShiftOutWarning::occur($this->id->toString(), array(
            'userId' => $member->getId(),
            'organizationId' => $this->getId(),
            'minCredits' => $minCredits,
            'minItems' => $minItems,
            'withinDays' => $withinDays,
            'by' => $by->getId(),
        )));
    }

	public function resetShiftOutWarning(User $member, User $by)
    {
        $this->recordThat(ResetShiftOutWarning::occur($this->id->toString(), array(
            'userId' => $member->getId(),
            'by' => $by->getId(),
        )));
    }

	/**
	 * @return \DateTime
	 */
	public function getCreatedAt()
	{
		return $this->createdAt;
	}

	/**
	 * @return array
	 */
	public function getMembers() {
		return $this->members;
	}

	/**
	 * @return array
	 */
	public function getAdmins() {
		return array_filter($this->members, function($profile) {
			return isset($profile['role']) && $profile['role'] == self::ROLE_ADMIN;
		});
	}

    protected function whenLaneAdded(LaneAdded $event)
    {
        $lane = new Lane($event->id(), $event->name());

        $this->lanes[$event->id()->toString()] = $lane;
    }

	protected function whenLaneUpdated(LaneUpdated $event)
    {
        $this->lanes[$event->id()->toString()]->update($event->name());
    }

	protected function whenLaneDeleted(LaneDeleted $event)
    {
        unset($this->lanes[$event->id()->toString()]);
    }

	protected function whenShiftOutWarning(ShiftOutWarning $event)
    {

    }

	protected function whenResetShiftOutWarning(ResetShiftOutWarning $event)
    {

    }

	protected function whenOrganizationCreated(OrganizationCreated $event)
	{
		$this->id = Uuid::fromString($event->aggregateId());
		$this->createdAt = $event->occurredOn();
	}

	protected function whenOrganizationUpdated(OrganizationUpdated $event)
    {
		$pl = $event->payload();


		if(array_key_exists('name', $pl)) {
			$this->name = $pl['name'];
		}

		if(array_key_exists('settingKey', $pl) && array_key_exists('settingValue', $pl)) {
			if(is_array($pl['settingValue'])){
				foreach ($pl['settingValue'] as $key=>$value){
					$this->settings[$pl['settingKey']][$key] = $value;
				}
			}else{
				$this->settings[$pl['settingKey']] = $pl['settingValue'];
			}
		}

		if (array_key_exists('syncErrorsNotification', $pl)) {
            $this->syncErrorsNotification = $pl['syncErrorsNotification'];
        }
	}

	protected function whenOrganizationAccountChanged(OrganizationAccountChanged $event)
    {
		$p = $event->payload();
		$this->accountId = Uuid::fromString($p['accountId']);
	}

	protected function whenOrganizationMemberAdded(OrganizationMemberAdded $event)
    {
		$p = $event->payload();
		$id = $p['userId'];
		$this->members[$id]['role'] = $p['role'];
	}

	protected function whenOrganizationMemberRoleChanged(OrganizationMemberRoleChanged $event)
    {
		$p = $event->payload();
		$id = $p['userId'];
		$this->members[$id]['role'] = $p['newRole'];
	}

	protected function whenOrganizationMemberRemoved(OrganizationMemberRemoved $event)
    {
		$id = $event->userId();
		unset($this->members[$id]);
	}

	protected function whenOrganizationMemberActivationChanged(OrganizationMemberActivationChanged $event)
    {
		$id = $event->userId();
		$this->members[$id]['active'] = $event->active();
    }
}