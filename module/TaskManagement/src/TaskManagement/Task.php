<?php

namespace TaskManagement;

use Application\DomainEntity;
use Application\DomainEntityUnavailableException;
use Application\DuplicatedDomainEntityException;
use Application\Entity\BasicUser;
use Application\Entity\User;
use Application\IllegalStateException;
use Application\InvalidArgumentException;
use People\MissingOrganizationMembershipException;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Event\TaskPositionUpdated;
use TaskManagement\Event\TaskRevertedToAccepted;
use TaskManagement\Event\TaskRevertedToCompleted;
use TaskManagement\Event\TaskUpdated;
use TaskManagement\Event\TaskMemberRemoved;

class Task extends DomainEntity implements TaskInterface
{
    const NOT_ESTIMATED = -1;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var Uuid
     */
    protected $streamId;

    /**
     * @var Uuid
     */
    protected $organizationId;

    protected $members = [];

    protected $organizationMembersApprovals=[];

    protected $organizationMembersAcceptances=[];

    protected $attachments;

    protected $lane;

    protected $position;

    /**
     * @var boolean
     */
    protected $is_decision = false;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var BasicUser
     */
    protected $createdBy;

    /**
     * @var \DateTime
     */
    protected $mostRecentEditAt;

    /**
     * @var \DateTime
     */
    protected $acceptedAt;

    /**
     * @var \DateTime
     */
    protected $completedAt;

    /**
     * @var \DateTime
     */
    protected $sharesAssignmentExpiresAt = null;

    public static function create(Stream $stream, $subject, BasicUser $createdBy, array $options = null)
    {
        $rv = new self();

        $decision = false;

        if (is_array($options) &&
            isset($options['decision']) &&
            $options['decision'] == 'true') {
            $decision = true;
        }

        $rv->recordThat(TaskCreated::occur(Uuid::uuid4()->toString(), [
            'status' => self::STATUS_IDEA,
            'organizationId' => $stream->getOrganizationId(),
            'streamId' => $stream->getId(),
            'by' => $createdBy->getId(),
            'userName' => $createdBy->getFirstname().' '.$createdBy->getLastname(),
            'decision' => $decision,
            'lane' => is_array($options) && isset($options['lane']) ? $options['lane'] : ''
        ]));

        $rv->setSubject($subject, $createdBy);

        return $rv;
    }

    public function delete(BasicUser $deletedBy)
    {
        $allowed_delete = [
            self::STATUS_IDEA,
            self::STATUS_OPEN,
            self::STATUS_ONGOING,
            self::STATUS_ARCHIVED
        ];

        if (!in_array($this->getStatus(), $allowed_delete, false)) {
            throw new IllegalStateException('Cannot delete a task in state '.$this->getStatus().'. Task '.$this->getId().' won\'t be deleted');
        }

        $this->recordThat(TaskDeleted::occur($this->id->toString(), array(
            'prevStatus' => $this->getStatus(),
            'by'  => $deletedBy->getId(),
            'subject' => $this->getSubject(),
            'partecipants' => $this->getMembers(),
            'organization' => $this->getOrganizationId()
        )));
    }

    /**
     * Set the attachments for the given tasks.
     *
     * Attachments are just a json blob coming from Google Drive. They are updated as a whole
     *
     * @param BasicUser $updatedBy the user performing the action
     * @param string    $jsonData  the json blob
     */
    public function setAttachments(BasicUser $updatedBy, $jsonData)
    {
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
                'attachments' => $jsonData,
                'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function getAttachments()
    {
        if (!$this->attachments) {
            return [];
        }

        if (is_array($this->attachments)) {
            return $this->attachments;
        }

        if (is_string($this->attachments)) {
            return json_decode($this->attachments);
        }
    }

    /**
     * Set the lane for the given tasks.
     *
     * @param string    $lane  the lane
     */
    public function setLane($lane, BasicUser $updatedBy)
    {
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
                'lane' => $lane,
                'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function getLane()
    {
        if (is_a($this->lane, Uuid::class)) {
            return $this->lane->toString();
        }

        return $this->lane;
    }

    public function update(array $data, BasicUser $updatedBy)
    {
        $e = TaskUpdated::happened(
            $this->id->toString(),
            $data['subject'],
            $data['description'],
            isset($data['lane']) ? $data['lane'] : null,
            $this->getLane(),
            $updatedBy->getId()
        );

        $this->recordThat($e);
    }

    /**
     * Set the position for the given tasks.
     *
     * @param integer	$position	the position on the kanbanize board
     */
    public function updatePosition($position, BasicUser $updatedBy)
    {
        $event = TaskPositionUpdated::happened(
            $this->id->toString(),
            $position,
            $this->getPosition(),
            $updatedBy->getId()
        );

        $this->recordThat($event);
    }

    /**
     * Set the position for the given tasks.
     *
     * @param integer	$position	the position on the kanbanize board
     */
    public function setPosition($position, BasicUser $updatedBy)
    {
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
                'position' => $position,
                'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function execute(BasicUser $executedBy)
    {
        //The status IDEA is provisional
        if (!in_array($this->status, [self::STATUS_OPEN, self::STATUS_COMPLETED])) {
            throw new IllegalStateException('Cannot execute a task in '.$this->status.' state');
        }
        $this->recordThat(TaskOngoing::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'by' => $executedBy->getId(),
                'userName' => $executedBy->getFirstname().' '.$executedBy->getLastname(),
        )));
        return $this;
    }

    public function complete(BasicUser $completedBy)
    {
        if (!in_array($this->status, [self::STATUS_ONGOING, self::STATUS_ACCEPTED])) {
            throw new IllegalStateException('Cannot complete a task in '.$this->status.' state');
        }

        if (is_null($this->getAverageEstimation())) {
            throw new IllegalStateException('Cannot complete a task with missing estimations by members');
        }
        $this->recordThat(TaskCompleted::occur($this->id->toString(), array(
            'organizationId' => $this->getOrganizationId(),
            'prevStatus' => $this->getStatus(),
            'by' => $completedBy->getId(),
        )));
        return $this;
    }

    public function accept(BasicUser $acceptedBy, \DateInterval $intervalForCloseTask = null)
    {
        if ($this->status != self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot accept a task in '.$this->status.' state');
        }
        $this->recordThat(TaskAccepted::occur($this->id->toString(), array(
            'organizationId' => $this->getOrganizationId(),
            'prevStatus' => $this->getStatus(),
            'by' => $acceptedBy->getId(),
            'intervalForCloseTask' => $intervalForCloseTask
        )));
        return $this;
    }

    public function reopen(BasicUser $reopenedBy)
    {
        if ($this->status != self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot reopen a task in '.$this->status.' state');
        }
        $this->recordThat(TaskReopened::occur($this->id->toString(), array(
            'organizationId' => $this->getOrganizationId(),
            'prevStatus' => $this->getStatus(),
            'by' => $reopenedBy->getId()
        )));
        return $this;
    }

    public function close(BasicUser $closedBy)
    {
        if ($this->status != self::STATUS_ACCEPTED) {
            throw new IllegalStateException('Cannot close a task in '.$this->status.' state');
        }

        $this->recordThat(TaskClosed::occur($this->id->toString(), array(
            'by' => $closedBy->getId(),
        )));

        return $this;
    }

    public function closeIfEnoughShares($minimumShares, BasicUser $closedBy)
    {
        $membersCount = $this->countMembers();
        $sharesCount = $this->countMembersShare();

        $minimumSharesToAutoClose = min($minimumShares, $membersCount);

        if ($sharesCount < $minimumSharesToAutoClose) {
            $this->recordThat(TaskNotClosedByTimebox::occur($this->id->toString(), array(
                    'by' => $closedBy->getId(),
            )));
            return $this;
        }

        $this->close($closedBy);

        $this->recordThat(TaskClosedByTimebox::occur($this->id->toString(), array(
            'by' => $closedBy->getId(),
        )));

        return $this;
    }

    public function open(BasicUser $executedBy)
    {
        if (!in_array($this->status, [self::STATUS_IDEA, self::STATUS_ONGOING])) {
            throw new IllegalStateException('Cannot open a task in '.$this->status.' state');
        }
        $this->recordThat(TaskOpened::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'by' => $executedBy->getId(),
        )));
        return $this;
    }

    public function revertToOpen($position, BasicUser $executedBy)
    {
        if (!in_array($this->status, [self::STATUS_ONGOING])) {
            throw new IllegalStateException('Cannot revert to open a task in '.$this->status.' state');
        }

        $this->recordThat(TaskRevertedToOpen::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'position' => $position,
                'by' => $executedBy->getId(),
        )));
        return $this;
    }

    public function revertToIdea(BasicUser $executedBy)
    {
        if (!in_array($this->status, [self::STATUS_OPEN, self::STATUS_ARCHIVED])) {
            throw new IllegalStateException('Cannot revert to idea a task in '.$this->status.' state');
        }

        $this->recordThat(TaskRevertedToIdea::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'by' => $executedBy->getId(),
        )));
        return $this;
    }

    public function revertToOngoing(BasicUser $executedBy)
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot revert to open a task in '.$this->status.' state');
        }

        $this->recordThat(TaskRevertedToOngoing::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'by' => $executedBy->getId(),
        )));

        return $this;
    }

    public function revertToCompleted(BasicUser $executedBy)
    {
        if ($this->status !== self::STATUS_ACCEPTED) {
            throw new IllegalStateException('Cannot revert to completed a task in '.$this->status.' state');
        }

        $e = TaskRevertedToCompleted::happened(
            $this->id->toString(),
            $this->getStatus(),
            $executedBy->getId()
        );

        $this->recordThat($e);

        return $this;
    }

    public function revertToAccepted(BasicUser $executedBy)
    {
        if ($this->status !== self::STATUS_CLOSED) {
            throw new IllegalStateException('Cannot revert to completed a task in '.$this->status.' state');
        }

        $e = TaskRevertedToAccepted::happened(
            $this->id->toString(),
            $this->getSubject(),
            $this->getMembersCredits(),
            $this->getOrganizationId(),
            $executedBy->getId()
        );

        $this->recordThat($e);

        return $this;
    }

    public function reject(BasicUser $executedBy)
    {
        if (!in_array($this->status, [self::STATUS_IDEA])) {
            throw new IllegalStateException('Cannot reject a task in state '.$this->getStatus().'. Task '.$this->getId().' won\'t be archived');
        }
        $this->recordThat(TaskArchived::occur($this->id->toString(), array(
                'prevStatus' => $this->getStatus(),
                'by' => $executedBy->getId(),
        )));
        return $this;
    }
    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject, BasicUser $updatedBy)
    {
        $s = is_null($subject) ? null : trim($subject);
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
            'subject' => $s,
            'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description, BasicUser $updatedBy)
    {
        $d = is_null($description) ? null : trim($description);
        $this->recordThat(TaskUpdated::occur($this->id->toString(), array(
                'description' => $d,
                'by' => $updatedBy->getId(),
        )));
        return $this;
    }

    public function changeStream(Stream $stream, BasicUser $updatedBy)
    {
        if ($this->status >= self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot set the task stream in '.$this->status.' state');
        }
        $payload = array(
                'streamId' => $stream->getId(),
                'by' => $updatedBy->getId(),
        );
        if (!is_null($this->streamId)) {
            $payload['prevStreamId'] = $this->streamId->toString();
        }
        $this->recordThat(TaskStreamChanged::occur($this->id->toString(), $payload));
        return $this;
    }

    /**
     * @return string
     */
    public function getStreamId()
    {
        return $this->streamId->toString();
    }

    /**
     * @return string
     */
    public function getOrganizationId()
    {
        return $this->organizationId->toString();
    }

    /**
     * @param User $user
     * @param string $role
     * @param BasicUser $addedBy
     * @throws IllegalStateException
     * @throws MissingOrganizationMembershipException
     * @throws DuplicatedDomainEntityException
     */
    public function addMember(User $user, $role = self::ROLE_MEMBER, BasicUser $addedBy = null)
    {
        if ($this->status >= self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot add a member to a task in '.$this->status.' state');
        }
        if (!$user->isMemberOf($this->getOrganizationId())) {
            throw new MissingOrganizationMembershipException($this->getOrganizationId(), $user->getId());
        }
        if (array_key_exists($user->getId(), $this->members)) {
            throw new DuplicatedDomainEntityException($this, $user);
        }

        $by = is_null($addedBy) ? $user : $addedBy;

        $this->recordThat(TaskMemberAdded::occur($this->id->toString(), array(
            'userId' => $user->getId(),
            'role' => $role,
            'by' => $by->getId(),
        )));
        return $this;
    }

    /**
     * @param User $member
     * @param BasicUser|null $removedBy
     */
    public function removeMember(User $member, BasicUser $removedBy = null)
    {
        // TODO: Integrare controllo per cui è possibile effettuare l'UNJOIN
        // solo nel caso in cui non sia stata ancora effettuata nessuna stima
        if (!array_key_exists($member->getId(), $this->members)) {
            throw new DomainEntityUnavailableException($this, $member);
        }

        $by = is_null($removedBy) ? $member : $removedBy;

        $event = TaskMemberRemoved::happened(
            $this->id->toString(),
            $this->getOrganizationId(),
            $member->getId(),
            $member->getFirstname().' '.$member->getLastname(),
            $member->getRole(),
            $by->getId()
        );

        $this->recordThat($event);
    }

    public function addEstimation($value, BasicUser $member)
    {
        if (!in_array($this->status, [self::STATUS_ONGOING])) {
            throw new IllegalStateException('Cannot estimate a task in the state '.$this->status.'.');
        }
        //check if the estimator is a task member
        if (!array_key_exists($member->getId(), $this->members)) {
            throw new DomainEntityUnavailableException($this, $member);
        }
        // TODO: Estimation need an id?
        $this->recordThat(EstimationAdded::occur($this->id->toString(), array(
            'by' => $member->getId(),
            'value'     => $value,
        )));
    }

    public function addApproval($vote, BasicUser $member, $description)
    {
        if (! in_array($this->status, [
                self::STATUS_IDEA
        ])) {
            throw new IllegalStateException('Cannot add an approval to item in a status different from idea');
        }
        if (array_key_exists($member->getId(), $this->organizationMembersApprovals)) {
            throw new DuplicatedDomainEntityException($this, $member);
        }

        $this->recordThat(ApprovalCreated::occur($this->id->toString(), array(
                'by' => $member->getId(),
                'vote' => $vote,
                'task-id' => $this->getId(),
                'description' => $description
        )));
    }

    public function addAcceptance($vote, BasicUser $member, $description)
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new IllegalStateException('Cannot add an acceptance to item in a status different from completed');
        }

        if (array_key_exists($member->getId(), $this->organizationMembersAcceptances)) {
            throw new DuplicatedDomainEntityException($this, $member);
        }

        $this->recordThat(AcceptanceCreated::occur($this->id->toString(), array(
                'by' => $member->getId(),
                'vote' => $vote,
                'task-id' => $this->getId(),
                'description' => $description
        )));
    }

    public function removeAcceptances(BasicUser $member)
    {
        /*
        if (! in_array($this->status, [
            self::STATUS_COMPLETED
        ])) {
            throw new IllegalStateException('Cannot remove acceptances from item in a status different from closed ['.$this->status.']');
        }
        */

        $this->recordThat(AcceptancesRemoved::occur($this->id->toString(), array(
                'by' => $member->getId(),
                'task-id' => $this->getId(),
        )));
    }

    /**
     *
     * @param array $shares Map of memberId and its share for each member
     * @param BasicUser $member
     * @throws IllegalStateException
     * @throws DomainEntityUnavailableException
     * @throws InvalidArgumentException
     */
    public function assignShares(array $shares, BasicUser $member)
    {
        if ($this->status != self::STATUS_ACCEPTED) {
            throw new IllegalStateException('Cannot assign shares in a task in status '.$this->status);
        }
        //check if the evaluator is a task member
        if (!array_key_exists($member->getId(), $this->members)) {
            throw new DomainEntityUnavailableException($this, $member);
        }

        $membersShares = array();
        foreach ($shares as $key => $value) {
            if (array_key_exists($key, $this->members)) {
                $membersShares[$key] = $value;
            }
        }

        /** With PHP 5.6 the previous chunk of code can be replaced with the following
        $membersShares = array_filter($shares, function($key, $value) {
            return array_key_exists($key, $this->members);
        }, ARRAY_FILTER_USE_BOTH);
        */

        $total = round(array_sum($membersShares), 3);
        if ($total != 1.0) {
            throw new InvalidArgumentException('The total amount of shares must be 100, '.($total*100).' found');
        }

        $missing = array_diff_key($this->members, $membersShares);
        if (count($missing) == 1) {
            throw new InvalidArgumentException('1 task member has missing share. Check the value for member ' . implode(',', array_keys($missing)));
        } elseif (count($missing) > 1) {
            throw new InvalidArgumentException(count($missing) . ' task members have missing shares. Check values for ' . implode(',', array_keys($missing)) . ' members');
        }

        $this->recordThat(SharesAssigned::occur($this->id->toString(), array(
            'shares' => $membersShares,
            'by' => $member->getId(),
        )));
    }

    public function skipShares(BasicUser $member)
    {
        if ($this->status != self::STATUS_ACCEPTED) {
            throw new IllegalStateException('Cannot assign shares in a task in status '.$this->status);
        }
        //check if the evaluator is a task member
        if (!array_key_exists($member->getId(), $this->members)) {
            throw new DomainEntityUnavailableException($this, $member);
        }

        $this->recordThat(SharesSkipped::occur($this->id->toString(), array(
            'by' => $member->getId(),
        )));
    }

    public function removeAllShares()
    {
        
    }

    /**
     * @param BasicUser $ex_owner
     * @throws MissingOrganizationMembershipException
     * @throws DomainEntityUnavailableException
     */
    public function changeOwner(BasicUser $newOwner, $exOwner, BasicUser $by)
    {
        if (!$newOwner->isMemberOf($this->getOrganizationId())) {
            throw new MissingOrganizationMembershipException(
                $this->getOrganizationId(), $newOwner->getId()
            );
        }

        if ($exOwner && (!$exOwner->isMemberOf($this->getOrganizationId()) || $exOwner->getId()!=$this->getOwner())) {
            throw new MissingOrganizationMembershipException(
                $this->getOrganizationId(), $exOwner->getId()
            );
        }

        $exOwnerId = $exOwnerName = null;
        if ($exOwner) {
            $exOwnerId = $exOwner->getId();
            $exOwnerName = $exOwner->getFirstname().' '.$exOwner->getLastname();
        }

        $this->recordThat(OwnerAdded::occur($this->id->toString(), array(
            'organizationId' => $this->getOrganizationId(),
            'ex_owner' => $exOwnerId,
            'ex_owner_name' => $exOwnerName,
            'new_owner' => $newOwner->getId(),
            'by' => $by->getId()
        )));

        if (!is_null($exOwner)) {
            $this->recordThat(OwnerRemoved::occur($this->id->toString(), array(
                'organizationId' => $this->getOrganizationId(),
                'ex_owner' => $exOwner->getId(),
                'ex_owner_name' => $exOwner->getFirstname().' '.$exOwner->getLastname(),
                'by' => $newOwner->getId()
            )));
        }
    }

    public function removeOwner(BasicUser $by)
    {
        $ex_owner = $this->getOwner();
        if (!is_null($ex_owner)) {
            $this->recordThat(OwnerRemoved::occur($this->id->toString(), array(
                'organizationId' => $this->getOrganizationId(),
                'ex_owner' => $ex_owner,
                'by' => $by->getId()
            )));
        }
    }

    /**
     * @return array
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * @return array
     */
    public function countMembers()
    {
        return count($this->members);
    }

    /**
     * @return array
     */
    public function getApprovals()
    {
        return $this->organizationMembersApprovals;
    }

    /**
     * @return array
     */
    public function getAcceptances()
    {
        return $this->organizationMembersAcceptances;
    }

    public function getAverageEstimation()
    {

        $tot = null;
        $estimationsCount = 0;
        $notEstimationCount = 0;
        foreach ($this->members as $member) {

            $estimation = isset($member['estimation']) ? $member['estimation'] : null;
            switch ($estimation) {
                case null:
                    break;
                case self::NOT_ESTIMATED:
                    $notEstimationCount++;
                    break;
                default:
                    $tot += $estimation;
                    $estimationsCount++;
            }
        }

        $membersCount = count($this->members);

        if ($notEstimationCount == $membersCount) {
            return self::NOT_ESTIMATED;
        }
        if (($estimationsCount + $notEstimationCount) == $membersCount || $estimationsCount > 2) {
            return round($tot / $estimationsCount, 2);
        }

        return null;
    }

    /**
     *
     * @param id|BasicUser $user
     * @return boolean
     */
    public function hasMember($user)
    {
        $key = $user instanceof BasicUser ? $user->getId() : $user;
        return isset($this->members[$key]);
    }
    /**
     *
     * @param string $role
     * @param id|BasicUser $user
     * @return boolean
     */
    public function hasAs($role, $user)
    {
        $key = $user instanceof BasicUser ? $user->getId() : $user;
        return isset($this->members[$key]) && $this->members[$key]['role'] == $role;
    }

    /**
     * @return array|null
     */
    public function getMembersCredits()
    {
        $credits = $this->getAverageEstimation();
        switch ($credits) {
            case null:
                return null;
            case self::NOT_ESTIMATED:
                $credits = 0;
        }

        $rv = array();
        foreach ($this->members as $id => $info) {
            $rv[$id] = isset($info['share']) ? round($credits * $info['share'], 2) : 0;
        }
        return $rv;
    }

    public function isSharesAssignmentCompleted()
    {
        foreach ($this->members as $member) {
            if (!isset($member['shares'])) {
                return false;
            }
        }
        return true;
    }

    public function getMemberRole($user)
    {
        $key = $user instanceof BasicUser ? $user->getId() : $user;
        if (isset($this->members[$key])) {
            return $this->members[$key]['role'];
        }
        return null;
    }

    public function getOwner()
    {
        foreach ($this->members as $key => $member) {
            if ($member['role'] == self::ROLE_OWNER) {
                return $key;
            }
        }
        return null;
    }

    public function isAuthor(User $creator)
    {
        return $this->createdBy->getId() === $creator->getId();
    }

    public function isDecision()
    {
        return $this->is_decision;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return 'task';
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getMostRecentEditAt()
    {
        return $this->mostRecentEditAt;
    }

    /**
     * @return \DateTime
     */
    public function getAcceptedAt()
    {
        return $this->acceptedAt;
    }

    /**
     * @return BasicUser
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @return \DateTime
     */
    public function getSharesAssignmentExpiresAt()
    {
        return $this->sharesAssignmentExpiresAt;
    }

    protected function whenTaskCreated(TaskCreated $event)
    {
        $this->id = Uuid::fromString($event->aggregateId());
        $p = $event->payload();
        $this->status = $p['status'];
        $this->organizationId = Uuid::fromString($p['organizationId']);
        $this->streamId = Uuid::fromString($p['streamId']);
        $this->createdAt = $this->mostRecentEditAt = $event->occurredOn();
        $this->createdBy = User::createUser(Uuid::fromString($p['by']));
        $this->is_decision = array_key_exists('decision', $p) ? $p['decision'] : false;
        $this->lane = array_key_exists('lane', $p) ? $p['lane'] : null;
    }

    protected function whenTaskOngoing(TaskOngoing $event)
    {
        $this->status = self::STATUS_ONGOING;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskCompleted(TaskCompleted $event)
    {
        $this->status = self::STATUS_COMPLETED;
        array_walk($this->members, function (&$value, $key) {
            unset($value['shares']);
            unset($value['share']);
        });
        $this->completedAt = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskAccepted(TaskAccepted $event)
    {
        $this->status = self::STATUS_ACCEPTED;

        $this->acceptedAt = $event->occurredOn();

        if (isset($event->payload()['intervalForCloseTask'])) {
            $sharesAssignmentExpiresAt = clone $event->occurredOn();
            $sharesAssignmentExpiresAt->add($event->payload()['intervalForCloseTask']);
            $this->sharesAssignmentExpiresAt = $sharesAssignmentExpiresAt;
        }
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskReopened(TaskReopened $event)
    {
        $this->status = self::STATUS_ONGOING;
        $this->mostRecentEditAt = $event->occurredOn();
        $this->completedAt = null;
    }

    protected function whenTaskClosed(TaskClosed $event)
    {
        $this->status = self::STATUS_CLOSED;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskClosedByTimebox(TaskClosedByTimebox $event)
    {
    }

    protected function whenTaskNotClosedByTimebox(TaskNotClosedByTimebox $event)
    {
    }

    protected function whenTaskDeleted(TaskDeleted $event)
    {
        $this->status = self::STATUS_DELETED;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskOpened(TaskOpened $event)
    {
        $this->status = self::STATUS_OPEN;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskRevertedToOpen(TaskRevertedToOpen $event)
    {
        $this->members = [];

        $this->status = self::STATUS_OPEN;
        $this->position = isset($event->payload()['position']) ? $event->payload()['position'] : null;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskRevertedToIdea(TaskRevertedToIdea $event)
    {
        $this->organizationMembersApprovals = [];

        $this->status = self::STATUS_IDEA;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskRevertedToOngoing(TaskRevertedToOngoing $event)
    {
        $this->status = self::STATUS_ONGOING;
        $this->organizationMembersAcceptances = [];
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskRevertedToCompleted(TaskRevertedToCompleted $event)
    {
        $this->status = self::STATUS_COMPLETED;
        $this->organizationMembersAcceptances = [];
        $this->mostRecentEditAt = $event->occurredOn();

        $unsetShares = function($member) {
            unset($member['shares'], $member['share'], $member['delta']);

            return $member;
        };

        $this->members = array_map($unsetShares, $this->members);
    }

    protected function whenTaskRevertedToAccepted(TaskRevertedToAccepted $event)
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->mostRecentEditAt = $event->occurredOn();

        $resetShareAndCredits = function($member) {
            unset($member['shares'], $member['share'], $member['delta'], $member['credits']);

            return $member;
        };

        $this->members = array_map($resetShareAndCredits, $this->members);
    }

    protected function whenTaskArchived(TaskArchived $event)
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskUpdated(TaskUpdated $event)
    {
        $pl = $event->payload();

        if (array_key_exists('subject', $pl)) {
            $this->subject = $pl['subject'];
        }
        if (array_key_exists('description', $pl)) {
            $this->description = $pl['description'];
        }
        if (array_key_exists('attachments', $pl)) {
            $this->attachments = $pl['attachments'];
        }

        if (array_key_exists('lane', $pl)) {
            $this->lane = $pl['lane'];
        }

        if (array_key_exists('position', $pl)) {
            $this->position = $pl['position'];
        }

        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskMemberAdded(TaskMemberAdded $event)
    {
        $p = $event->payload();
        $id = $p['userId'];
        $this->members[$id]['id'] = $id;
        $this->members[$id]['role'] = $p['role'];
        $this->members[$id]['createdAt'] = $event->occurredOn();

        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskMemberRemoved(TaskMemberRemoved $event)
    {
        $id = $event->userId();

        $resetShareAndCredits = function($member) {
            unset($member['shares'], $member['share'], $member['delta']);

            return $member;
        };

        $this->members = array_map($resetShareAndCredits, $this->members);
        unset($this->members[$id]);

        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskOwnerAdded(TaskMemberAdded $event)
    {
        $p = $event->payload();
        $id = $p['userId'];
        $this->members[$id]['id'] = $id;
        $this->members[$id]['role'] = TaskMember::ROLE_OWNER;
        $this->members[$id]['createdAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    /**
     * Maybe this function is not useful
     */
    protected function whenTaskOwnerRemoved(TaskMemberRemoved $event)
    {
        $p = $event->payload();
        $id = $p['userId'];
        $this->members[$id]['id'] = $id;
        $this->members[$id]['role'] = TaskMember::ROLE_MEMBER;
        $this->members[$id]['createdAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenOwnerAdded(OwnerAdded $event)
    {
        $p = $event->payload();
        $id = $p['new_owner'];
        $this->members[$id]['id'] = $id;
        $this->members[$id]['role'] = TaskMember::ROLE_OWNER;
        $this->members[$id]['createdAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenOwnerRemoved(OwnerRemoved $event)
    {
        $p = $event->payload();
        $id = $p['ex_owner'];
        $this->members[$id]['id'] = $id;
        $this->members[$id]['role'] = TaskMember::ROLE_MEMBER;
        $this->members[$id]['createdAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskStreamChanged(TaskStreamChanged $event)
    {
        $p = $event->payload();
        $this->streamId = Uuid::fromString($p['streamId']);
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenEstimationAdded(EstimationAdded $event)
    {
        $p = $event->payload();
        $id = $p['by'];
        $this->members[$id]['estimation'] = $p['value'];
        $this->members[$id]['estimatedAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenApprovalCreated(ApprovalCreated $event)
    {
        $p = $event->payload();
        $id = $p ['by'];
        $this->organizationMembersApprovals [$id] ['approval'] = $p ['vote'];
        $this->organizationMembersApprovals [$id] ['approvalGeneratedAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenAcceptanceCreated(AcceptanceCreated $event)
    {
        $p = $event->payload();
        $id = $p ['by'];
        $this->organizationMembersAcceptances [$id] ['acceptance'] = $p['vote'];
        $this->organizationMembersAcceptances [$id] ['acceptanceDescription'] = $p['description'];
        $this->organizationMembersAcceptances [$id] ['acceptanceGeneratedAt'] = $event->occurredOn();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenAcceptancesRemoved(AcceptancesRemoved $event)
    {
        unset($this->organizationMembersAcceptances);
        $this->organizationMembersAcceptances = [];
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenSharesAssigned(SharesAssigned $event)
    {
        $p = $event->payload();
        $id = $p['by'];
        $this->members[$id]['shares'] = $p['shares'];
        $shares = $this->getMembersShare();
        if (count($shares) > 0) {
            foreach ($shares as $key => $value) {
                $this->members[$key]['share'] = $value;
                if (isset($this->members[$key]['shares'][$key])) {
                    $this->members[$key]['delta'] = $this->members[$key]['shares'][$key] - $value;
                }
            }
        }
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenSharesSkipped(SharesSkipped $event)
    {
        $p = $event->payload();
        $id = $p['by'];
        foreach ($this->members as $key => $value) {
            $this->members[$id]['shares'][$key] = null;
        }
        $shares = $this->getMembersShare();
        if (count($shares) > 0) {
            foreach ($shares as $key => $value) {
                $this->members[$key]['share'] = $value;
            }
        }
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function whenTaskPositionUpdated(TaskPositionUpdated $event)
    {
        $this->position = $event->position();
        $this->mostRecentEditAt = $event->occurredOn();
    }

    protected function getMembersShare()
    {
        $rv = array();
        $evaluators = 0;
        foreach ($this->members as $evaluatorId => $info) {
            if (isset($info['shares'][$evaluatorId])) {
                $evaluators++;
                foreach ($info['shares'] as $valuedId => $value) {
                    $rv[$valuedId] = isset($rv[$valuedId]) ? $rv[$valuedId] + $value : $value;
                }
            }
        }
        if ($evaluators > 0) {
            array_walk($rv, function (&$value, $key) use ($evaluators) {
                $value = round($value / $evaluators, 4);
            });
        }
        return $rv;
    }

    public function countMembersShare()
    {
        $rv = array();
        $evaluators = 0;
        foreach ($this->members as $evaluatorId => $info) {
            if (isset($info['shares'][$evaluatorId])) {
                $evaluators++;
            }
        }
        return $evaluators;
    }

    /**
     * Returns the string identifier of the Resource
     *
     * @return string
     */
    public function getResourceId()
    {
        return 'Ora\Task';
    }
    /**
     *
     * @param id|BasicUser $user
     * @return boolean
     */
    public function areSharesAssignedFromMember($user)
    {
        $key = $user instanceof BasicUser ? $user->getId() : $user;
        return isset($this->members[$key]['shares']);
    }

    public function assignCredits(BasicUser $by)
    {
        $this->recordThat(CreditsAssigned::occur($this->id->toString(), array(
                'credits' => $this->getMembersCredits(),
                'taskId' => $this->getId(),
                'organizationId' => $this->getOrganizationId(),
                'by' => $by->getId()
        )));
        return $this;
    }

    protected function whenCreditsAssigned(CreditsAssigned $event)
    {
        foreach($this->getMembersCredits() as $memberId => $credits ) {
            $this->members[$memberId]['credits'] = $credits;
        }

        $this->mostRecentEditAt = $event->occurredOn();
    }

    public function isSharesAssignmentExpired(\DateTime $ref)
    {
        if (is_null($this->sharesAssignmentExpiresAt)) {
            return false;
        }
        return $ref > $this->sharesAssignmentExpiresAt;
    }
}
