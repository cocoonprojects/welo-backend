<?php

namespace FlowManagement\Service;

use Application\Service\UserService;
use ClassesWithParents\E;
use Doctrine\ORM\EntityManager;
use FlowManagement\Entity\ItemDeletedCard;
use FlowManagement\Entity\ItemMemberAddedCard;
use People\Service\OrganizationService;
use Prooph\EventStore\EventStore;
use TaskManagement\TaskArchived;
use TaskManagement\TaskClosed;
use TaskManagement\TaskCreated;
use TaskManagement\TaskCompleted;
use TaskManagement\TaskDeleted;
use TaskManagement\TaskOpened;
use TaskManagement\TaskAccepted;
use TaskManagement\TaskReopened;
use TaskManagement\TaskOngoing;
use TaskManagement\OwnerAdded;
use TaskManagement\TaskMemberAdded;
use TaskManagement\Event\TaskMemberRemoved;
use FlowManagement\Entity\ItemClosedCard;
use FlowManagement\FlowCardInterface;
use Rhumsaa\Uuid\Uuid;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use TaskManagement\Service\TaskService;
use People\Entity\OrganizationMembership;

class ItemCommandsListener implements ListenerAggregateInterface {
	
	protected $listeners = [];

	/**
	 * @var FlowService
	 */
	private $flowService;
	/**
	 * @var OrganizationService
	 */
	private $organizationService;
	/**
	 * @var UserService
	 */
	private $userService;
	/**
	 * @var EventStore
	 */
	private $transactionManager;
	/**
	 * @var TaskService
	 */
	private $taskService;


	private $entityManager;

	public function __construct(
	    FlowService $flowService,
        OrganizationService $organizationService,
        UserService $userService,
        EventStore $transactionManager,
        TaskService $taskService,
        EntityManager $entityManager)
    {
		$this->flowService = $flowService;
		$this->organizationService = $organizationService;
		$this->userService = $userService;
		$this->transactionManager = $transactionManager;
		$this->taskService = $taskService;
		$this->entityManager = $entityManager;

		$this->canVoteRoles = [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER];
	}
	
	public function attach(EventManagerInterface $events) {
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskCreated::class, array($this, 'processItemCreated'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskArchived::class, array($this, 'processIdeaVotingClosed'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskOpened::class, array($this, 'processIdeaVotingClosed'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskOngoing::class, array($this, 'processItemOngoing'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskCompleted::class, array($this, 'processItemCompleted'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskAccepted::class, array($this, 'processItemCompletedVotingClosed'));
		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskReopened::class, array($this, 'processItemCompletedReopened'));
		$this->listeners [] = $events->getSharedManager()->attach(Application::class, OwnerAdded::class, array($this, 'processItemOwnerChanged'));
		$this->listeners [] = $events->getSharedManager()->attach(Application::class, TaskMemberAdded::class, array($this, 'processItemMemberAdded'));
		$this->listeners [] = $events->getSharedManager()->attach(Application::class, TaskMemberRemoved::class, array($this, 'processItemMemberRemoved'));
		$this->listeners [] = $events->getSharedManager()->attach(Application::class, TaskClosed::class, array($this, 'processItemClosed'));
		$this->listeners [] = $events->getSharedManager()->attach(Application::class, TaskDeleted::class, array($this, 'processItemDeleted'));
	}
	
	public function processItemCreated(Event $event){
		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];
		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));
		$orgMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null, $this->canVoteRoles);
		$createdBy = $this->userService->findUser($event->getParam('by'));
		$params = [$this->flowService, $itemId, $organization, $createdBy];
		array_walk($orgMemberships, function($member) use($params){
			$flowService = $params[0];
			$itemId = $params[1];
			$organization = $params[2];
			$createdBy = $params[3];
			$flowService->createVoteIdeaCard($member->getMember(), $itemId, $organization->getId(), $createdBy);
		});
	}
	
	public function processItemOngoing(Event $event){
		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];
		$item = $this->taskService->getTask($itemId);		

		$exOwner = $this->userService->findUser($item->getOwner());
		$changedBy = $this->userService->findUser($event->getParam('by'));

		$this->transactionManager->beginTransaction();
		try {
			$item->changeOwner($changedBy, $exOwner, $changedBy);
			$this->transactionManager->commit();
		}catch( \Exception $e ) {
			$this->transactionManager->rollback();
			throw $e;
		}
	}
	
	public function processItemCompleted(Event $event){
		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];
		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));
		$orgMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null, $this->canVoteRoles);
		$createdBy = $this->userService->findUser($event->getParam('by'));
		$params = [$this->flowService, $itemId, $organization, $createdBy];
		array_walk($orgMemberships, function($member) use($params){
			$flowService = $params[0];
			$itemId = $params[1];
			$organization = $params[2];
			$completedBy = $params[3];
			$flowService->createVoteCompletedItemCard($member->getMember(), $itemId, $organization->getId(), $completedBy);
		});
	}	
	
	public function processIdeaVotingClosed(Event $event) {
		$streamEvent = $event->getTarget();
		$item = $this->taskService->findTask($streamEvent->metadata()['aggregate_id']);
		//recupero le card del flow che sono associate a questo item
		$flowCards = $this->flowService->findFlowCardsByItem($item);
		$params = [$this->flowService, $this->transactionManager];
		array_walk($flowCards, function($card) use($params){
			$flowService = $params[0];
			$transactionManager = $params[1];
			$wmCard = $flowService->getCard($card->getId());
			$transactionManager->beginTransaction();
			try {
				$wmCard->hide();
				$transactionManager->commit();
			}catch( \Exception $e ) {
				$transactionManager->rollback();
				throw $e;
			}
		});
	}

	public function processItemCompletedVotingClosed(Event $event){
		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];

		$item = $this->taskService->findTask($itemId);

		//recupero le card del flow che sono associate a questo item
		$flowCards = $this->flowService->findFlowCardsByItem($item);

		// chiusura delle precedenti card aperte per questo item
		$params = [$this->flowService, $this->transactionManager];
		array_walk($flowCards, function($card) use($params){
			$flowService = $params[0];
			$transactionManager = $params[1];

			$wmCard = $flowService->getCard($card->getId());
			$transactionManager->beginTransaction();
			try {
				$wmCard->hide();
				$transactionManager->commit();
			}catch( \Exception $e ) {
				$transactionManager->rollback();
				throw $e;
			}
		});

		// creazione di nuove card per notificare la chiusura del processo di voto
		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));
		$orgMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null, $this->canVoteRoles);
		$createdBy = $this->userService->findUser($event->getParam('by'));
		$params = [$this->flowService, $itemId, $organization, $createdBy];
		array_walk($orgMemberships, function($member) use($params){
			$flowService = $params[0];
			$itemId = $params[1];
			$organization = $params[2];
			$completedBy = $params[3];
			$flowService->createVoteCompletedItemVotingClosedCard($member->getMember(), $itemId, $organization->getId(), $completedBy);
		});
	}

	public function processItemCompletedReopened(Event $event){
		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];
		$item = $this->taskService->findTask($itemId);

		//recupero le card del flow che sono associate a questo item
		$flowCards = $this->flowService->findFlowCardsByItem($item);

		// chiusura delle precedenti card aperte per questo item
		$params = [$this->flowService, $this->transactionManager];
		array_walk($flowCards, function($card) use($params){
			$flowService = $params[0];
			$transactionManager = $params[1];
			$wmCard = $flowService->getCard($card->getId());
			$transactionManager->beginTransaction();
			try {
				$wmCard->hide();
				$transactionManager->commit();
			}catch( \Exception $e ) {
				$transactionManager->rollback();
				throw $e;
			}
		});

		// creazione di nuove card per notificare la chiusura del processo di voto
		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));
		$orgMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null, $this->canVoteRoles);
		$reopenedBy = $this->userService->findUser($event->getParam('by'));
		$params = [$this->flowService, $itemId, $organization, $reopenedBy];
		array_walk($orgMemberships, function($member) use($params){
			$flowService = $params[0];
			$itemId = $params[1];
			$organization = $params[2];
			$completedBy = $params[3];
			$flowService->createVoteCompletedItemReopenedCard($member->getMember(), $itemId, $organization->getId(), $completedBy);
		});		
	}

	public function processItemOwnerChanged(Event $event) {
		if (is_null($event->getParam('ex_owner'))) {
			return;
		}

		$organization = $this->organizationService->findOrganization($event->getParam('organizationId'));

		$streamEvent = $event->getTarget();
		$itemId = $streamEvent->metadata()['aggregate_id'];

        $item = $this->taskService->getTask($itemId);
        $itemMembers = $item->getMembers();

		$changedBy = $this->userService->findUser($event->getParam('by'));

        foreach ($itemMembers as $member) {
            $recipient = $this->userService->findUser($member['id']);

            $this->flowService->createItemOwnerChangedCard($recipient, $itemId, $organization->getId(), $changedBy);

        }
	}

	public function processItemMemberAdded(Event $event) {
        $metaData = $event->getTarget()->metadata();
        $payload = $event->getTarget()->payload();

        $memberId = $payload['userId'];
        if (is_null($memberId)) {
			return;
		}

		$itemId = $metaData['aggregate_id'];
        $item = $this->taskService->findTask($itemId);

        $owner = $item->getOwner();

        if ($owner) {
            $newMember = $this->userService->findUser($memberId);
            $addedBy = $this->userService->findUser($payload['by']);

            $this->flowService->createItemMemberAddedCard($owner->getUser(), $itemId, $newMember, $item->getOrganizationId(), $addedBy);
        }
	}

	public function processItemMemberRemoved(TaskMemberRemoved $event) {
		if (is_null($event->userId())) {
			return;
		}

		$itemId = $event->aggregateId();
        $item = $this->taskService->getTask($itemId);

		$exMember = $this->userService->findUser($event->userId());
		$changedBy = $this->userService->findUser($event->by());

		$organization = $this->organizationService->findOrganization($event->organizationId());
		$orgAdminsMemberships = $this->organizationService->findOrganizationMemberships($organization, null, null, [OrganizationMembership::ROLE_ADMIN]);

		$exMemberId = $exMember->getId();
		$exMemberFound = false;

		$item->removeAcceptances($exMember);
		$item->removeAllShares();

        $ownerId = $item->getOwner();
        $owner = $ownerId ? $this->userService->findUser($ownerId) : null;
        $ownerFound = false;

		foreach ($orgAdminsMemberships as $member) {
			$_member = $member->getMember();
			$orgAdmins[] = $_member;

			if ($_member->getId() == $exMemberId) { $exMemberFound = true; }

			if ($_member->getId() == $ownerId) { $ownerFound = true; }
		}

		if (!$ownerFound && $owner) {
		    $orgAdmins[] = $owner;
        }

		if (!$exMemberFound) {
            $orgAdmins[] = $exMember;
        }

		$params = [$this->flowService, $itemId, $organization, $changedBy, $exMember];
		array_walk($orgAdmins, function($recipient) use($params){
			$flowService = $params[0];
			$itemId = $params[1];
			$organization = $params[2];
			$changedBy = $params[3];
			$exMember = $params[4];

            $flowService->createItemMemberRemovedCard($recipient, $itemId, $exMember, $organization->getId(), $changedBy);
		});
	}

	public function processItemClosed(Event $event) {
        $streamEvent = $event->getTarget();
        $itemId = $streamEvent->metadata()['aggregate_id'];
        $item = $this->taskService->findTask($itemId);

        $by = $this->userService->findUser($event->getParam('by'));

        $data = [
            'orgId' => $item->getOrganizationId(),
        ];

        $flowCard = new ItemClosedCard(Uuid::uuid4(), $by);
        $flowCard->setContent(FlowCardInterface::ITEM_CLOSED_CARD, $data);
        $flowCard->setItem($item);
        $flowCard->setCreatedBy($by);

        $this->entityManager->persist($flowCard);
        $this->entityManager->flush();
    }

    public function processItemDeleted(Event $event) {
        $payload = $event->getTarget()->payload();

        $by = $this->userService->findUser($event->getParam('by'));

        $partecipants = $this->userService->findByIds(array_keys($payload['partecipants']));
        $admins = $this->organizationService->findOrganizationAdmins($payload['organization']);

        $recipients = [];

        foreach ($partecipants as $partecipant) {
            $recipients[$partecipant->getId()] = $partecipant;
        }

        foreach ($admins as $admin) {
            $recipients[$admin->getId()] = $admin;
        }

        foreach ($recipients as $recipient) {
            $flowCard = ItemDeletedCard::create(Uuid::uuid4(), $payload['subject'], $recipient, $by);
            $this->entityManager->persist($flowCard);
        }

        $this->entityManager->flush();
    }

	public function detach(EventManagerInterface $events){
		foreach ( $this->listeners as $index => $listener ) {
			if ($events->detach ( $listener )) {
				unset ( $this->listeners [$index] );
			}
		}
	}
}