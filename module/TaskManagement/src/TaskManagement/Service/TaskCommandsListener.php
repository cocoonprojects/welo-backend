<?php
namespace TaskManagement\Service;

use Application\Entity\User;
use Application\Service\ReadModelProjector;
use Doctrine\ORM\EntityManager;
use Prooph\EventStore\Stream\StreamEvent;
use TaskManagement\Task as WriteModelTask;
use TaskManagement\Entity\Estimation;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\Task;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Entity\Vote;
use Kanbanize\Service\KanbanizeService;
use People\Service\OrganizationService;

class TaskCommandsListener extends ReadModelProjector {

	CONST KANBANIZE_SETTINGS = 'kanbanize';

	private $kanbanizeService;

	private $orgService;

	public function __construct(
	    EntityManager $entityManager,
        KanbanizeService $kanbanizeService,
        OrganizationService $orgService) {

	    parent::__construct($entityManager);

		$this->kanbanizeService = $kanbanizeService;
		$this->orgService = $orgService;
	}

	protected function onTaskCreated(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$type = $event->metadata ()['aggregate_type'];

		if ($type == WriteModelTask::class) {

			$stream = $this->entityManager
				->find(Stream::class, $event->payload()['streamId']);

			if (is_null ( $stream )) {
				return;
			}

			$createdBy = $this->entityManager
			    ->find(User::class, $event->payload()['by']);

			$decision = $event->payload()['decision'];

			$entity = new Task($id, $stream, $decision);
			$entity->setLane($event->payload()['lane']);

			$entity->setStatus($event->payload()['status'])
				->setCreatedAt($event->occurredOn())
				->setCreatedBy($createdBy)
				->setMostRecentEditAt($event->occurredOn())
				->setMostRecentEditBy($createdBy);

			$this->entityManager->persist($entity);
		}
	}

	protected function onTaskUpdated(StreamEvent $event) {

        $id = $event->metadata ()['aggregate_id'];
		$entity = $this->entityManager->find ( Task::class, $id );

		if (is_null ( $entity )) {
			return;
		}

		$updatedBy = $this->entityManager->find ( User::class, $event->payload ()['by'] );

		if (isset ( $event->payload()['subject'] )) {
			$entity->setSubject ( $event->payload ()['subject'] );
		}

		if (isset ( $event->payload()['description'] )) {
			$entity->setDescription ( $event->payload ()['description'] );
		}

		if(isset($event->payload()['attachments'])) {
			$entity->setAttachments($event->payload()['attachments']);
		}

		if(isset($event->payload()['position'])) {
			$entity->setPosition($event->payload()['position']);
		}

		if(isset($event->payload()['lane']) && $entity->getLane() !== $event->payload()['lane']) {
            $entity->setLane($event->payload()['lane']);

            if ($entity->getType() == "kanbanizetask") {
                $kanbanizeStream = $entity->getStream();
                $kanbanizeBoardId = $kanbanizeStream->getBoardId();

                $org = $this->orgService->findOrganization($entity->getOrganizationId());
                $kanbanizeSettings = $org->getSettings($this::KANBANIZE_SETTINGS);

                if (is_null ( $kanbanizeSettings ) || empty ( $kanbanizeSettings )) {
                    return;
                }

                $mapping = $kanbanizeSettings ['boards'] [$kanbanizeBoardId] ['columnMapping'];
                $status = array_search($entity->getStatus(), $mapping);

                $this->kanbanizeService->initApi ( $kanbanizeSettings ['apiKey'], $kanbanizeSettings ['accountSubdomain'] );
                $this->kanbanizeService->loadLanesFromKanbanize($kanbanizeBoardId);
                $this->kanbanizeService->moveTaskonKanbanize($entity, $status, $kanbanizeBoardId);
            }

		}

		$entity->setMostRecentEditAt ( $event->occurredOn () );
		$entity->setMostRecentEditBy ( $updatedBy );

		$this->entityManager->persist ( $entity );
	}


	protected function onTaskStreamChanged(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$entity = $this->entityManager->find ( Task::class, $id );
		if (is_null ( $entity )) {
			return;
		}
		$streamId = $event->payload ()['streamId'];
		$stream = $this->entityManager->find ( Stream::class, $streamId );
		if (is_null ( $stream )) {
			return;
		}
		$updatedBy = $this->entityManager->find ( User::class, $event->payload ()['by'] );

		$entity->setStream ( $stream );
		$entity->setMostRecentEditAt ( $event->occurredOn () );
		$entity->setMostRecentEditBy ( $updatedBy );
		$this->entityManager->persist ( $entity );
	}

	protected function onTaskMemberAdded(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$entity = $this->entityManager->find ( Task::class, $id );

		if (is_null ( $entity )) {
			return;
		}
		$user = $this->entityManager->find ( User::class, $event->payload ()['userId'] );
		$addedBy = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$role = $event->payload ()['role'];

		$entity->addMember ( $user, $role, $addedBy, $event->occurredOn () )->setMostRecentEditAt ( $event->occurredOn () )->setMostRecentEditBy ( $addedBy );
		$this->entityManager->persist ( $entity );
	}

	protected function onTaskMemberRemoved(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$entity = $this->entityManager->find ( Task::class, $id );
		$member = $this->entityManager->find ( User::class, $event->payload ()['userId'] );
		$removedBy = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$entity->removeMember ( $member )->setMostRecentEditAt ( $event->occurredOn () )->setMostRecentEditBy ( $removedBy );
		$this->entityManager->persist ( $entity );
	}

	protected function onTaskDeleted(StreamEvent $event)
    {
        $task = $this->entityManager->find(Task::class, $event->metadata()['aggregate_id']);

		if ($task === null) {
			return;
		}

		$this->entityManager->remove($task);

		if ($task->getType() == 'kanbanizetask') {
		    $this->deleteOnKanbanize($task);
        }
	}

	protected function onTaskOpened(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus ( Task::STATUS_OPEN );
		$user = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$task->setMostRecentEditBy ( $user );
		$task->setMostRecentEditAt ( $event->occurredOn () );
		$this->entityManager->persist ( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize($task);
		}
	}

	protected function onTaskReopened(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];

		$task = $this->entityManager->find( Task::class, $id );
		$user = $this->entityManager->find( User::class, $event->payload()['by'] );

		$task->revertToOngoing($user, $event->occurredOn());

		$this->entityManager->persist( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize($task);
		}
	}

	protected function onTaskRevertedToOpen(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$position = $event->payload()['position'];
		$task = $this->entityManager->find( Task::class, $id );
		$user = $this->entityManager->find( User::class, $event->payload ()['by'] );

		$task->revertToOpen($user, $position, $event->occurredOn());

		$this->entityManager->persist( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize($task);
		}
	}

	protected function onTaskRevertedToIdea(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find( Task::class, $id );
		$user = $this->entityManager->find( User::class, $event->payload ()['by'] );

		$task->revertToIdea($user, $event->occurredOn());

		$this->entityManager->persist( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize($task);
		}
	}

	protected function onTaskRevertedToOngoing(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find( Task::class, $id );
		$user = $this->entityManager->find( User::class, $event->payload ()['by'] );

		$task->revertToOngoing($user, $event->occurredOn());

		$this->entityManager->persist( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize($task);
		}
	}

	protected function onTaskArchived(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus ( Task::STATUS_ARCHIVED );
		$user = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$task->setMostRecentEditBy ( $user );
		$task->setMostRecentEditAt ( $event->occurredOn () );
		$this->entityManager->persist ( $task );
		if ($task->getType () == "kanbanizetask") {
			$this->updateOnKanbanize ( $task );
		}
	}

	protected function onEstimationAdded(StreamEvent $event) {
		$memberId = $event->payload()['by'];
		$user = $this->entityManager->find(User::class, $memberId);
		if(is_null($user)) {
			return;
		}
		$id = $event->metadata()['aggregate_id'];
		$taskMember = $this->entityManager->find(TaskMember::class, ['task' => $id, 'user' => $user]);
		$value = $event->payload()['value'];
		$taskMember->setEstimation(new Estimation($value, $event->occurredOn()));
		$this->entityManager->persist($taskMember);
	}

	protected function onApprovalCreated(StreamEvent $event) {
		$memberId = $event->payload ()['by'];
		$description = $event->payload ()['description'];
		$user = $this->entityManager->find ( User::class, $memberId );
		if (is_null ( $user )) {
			return;
		}
		$id = $event->metadata ()['aggregate_id'];
		$taskId = $event->payload ()['task-id'];
		$task = $this->entityManager->find ( Task::class, $taskId );
		$vote = new Vote ( $event->occurredOn () );
		$vote->setValue ( $event->payload ()['vote'] );
		$task->addApproval ( $vote, $user, $event->occurredOn (), $description );
		$this->entityManager->persist ( $task );
	}

	protected function onAcceptanceCreated(StreamEvent $event) {
		$memberId = $event->payload()['by'];
		$description = $event->payload ()['description'];
		$user = $this->entityManager->find ( User::class, $memberId );
		if (is_null($user)) {
			return;
		}
		$id = $event->metadata ()['aggregate_id'];
		$taskId = $event->payload ()['task-id'];
		$task = $this->entityManager->find ( Task::class, $taskId );
		$vote = new Vote ( $event->occurredOn() );
		$vote->setValue ( $event->payload ()['vote'] );
		$task->addAcceptance( $vote, $user, $event->occurredOn (), $description );
		$this->entityManager->persist ( $task );
	}

	protected function onAcceptancesRemoved(StreamEvent $event) {
		$memberId = $event->payload()['by'];
		$user = $this->entityManager->find( User::class, $memberId );
		if (is_null( $user )) {
			return;
		}
		$taskId = $event->payload()['task-id'];
		$task = $this->entityManager->find( Task::class, $taskId );

		$task->removeAcceptances( $user );
		$this->entityManager->persist( $task );
	}

	protected function onTaskCompleted(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];

		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus( Task::STATUS_COMPLETED );
		$task->resetShares();

		$user = $this->entityManager->find( User::class, $event->payload()['by'] );

		$task->setMostRecentEditBy( $user );
		$task->setMostRecentEditAt( $event->occurredOn() );
		$task->setCompletedAt( $event->occurredOn() );
		$task->resetAcceptedAt();

		$this->entityManager->persist( $task );

		if ($task->getType() == "kanbanizetask") {
			$this->updateOnKanbanize( $task );
		}
	}

	protected function onTaskAccepted(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus ( Task::STATUS_ACCEPTED );
		$user = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$task->setMostRecentEditBy ( $user );
		$task->setMostRecentEditAt ( $event->occurredOn () );
		$task->setAcceptedAt ( $event->occurredOn () );
		if (isset ( $event->payload ()['intervalForCloseTask'] )) {
			$sharesAssignmentExpiresAt = clone $event->occurredOn ();
			$sharesAssignmentExpiresAt->add ( $event->payload ()['intervalForCloseTask'] );
			$task->setSharesAssignmentExpiresAt ( $sharesAssignmentExpiresAt );
		}
		$this->entityManager->persist ( $task );
		if ($task->getType () == "kanbanizetask") {
			$this->updateOnKanbanize ( $task );
		}
	}

	protected function onTaskClosed(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus ( Task::STATUS_CLOSED );
		$user = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$task->setMostRecentEditBy ( $user );
		$task->setMostRecentEditAt ( $event->occurredOn () );
		$this->entityManager->persist ( $task );
		if ($task->getType () == "kanbanizetask") {
			$this->updateOnKanbanize ( $task );
		}
	}

	protected function onTaskOngoing(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$task->setStatus ( Task::STATUS_ONGOING );
		$user = $this->entityManager->find ( User::class, $event->payload ()['by'] );
		$task->setMostRecentEditBy ( $user );
		$task->setMostRecentEditAt ( $event->occurredOn () );
		$this->entityManager->persist ( $task );

		if ($task->getType () == "kanbanizetask") {
			$this->updateOnKanbanize ( $task );
		}
	}

	protected function onSharesAssigned(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$evaluator = $task->getMember ( $event->payload ()['by'] );
		$evaluator->resetShares ();
		$shares = $event->payload ()['shares'];
		foreach ( $shares as $key => $value ) {
			$valued = $task->getMember ( $key );
			$evaluator->assignShare ( $valued, $value, $event->occurredOn () );
		}
		$this->entityManager->persist ( $task );
	}

	protected function onSharesSkipped(StreamEvent $event) {
		$id = $event->metadata ()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $id );
		$evaluator = $task->getMember ( $event->payload ()['by'] );
		$evaluator->resetShares ();
		foreach ( $task->getMembers () as $valued ) {
			$evaluator->assignShare ( $valued, null, $event->occurredOn () );
		}
		$this->entityManager->persist ( $task );
	}

	protected function onCreditsAssigned(StreamEvent $event) {

        $taskId = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find ( Task::class, $taskId );
		$credits = $event->payload()['credits'];

		foreach($task->getMembers () as $member ) {
			$member->setCredits($credits[$member->getUser()->getId()]);
			$this->entityManager->persist($member);
		}

        $orgId = $event->payload()['organizationId'];

        foreach ($credits as $userId => $credit)
        {
            $this->orgService
                 ->updateMemberContribution($orgId, $userId, $taskId, $credit, $event->occurredOn());
        }

	}

	protected function onOwnerAdded(StreamEvent $event) {

		$id = $event->metadata()['aggregate_id'];
		$new_owner_id = $event->payload()['new_owner'];
		$by_id = $event->payload()['by'];


		if (is_null($new_owner_id)) {
			return;
		}

		$new_task_owner = $this->entityManager
			->find(TaskMember::class, ['task' => $id, 'user' => $new_owner_id]);

		$entity = $this->entityManager
					   ->find(Task::class, $id);

		if (is_null($entity)) {
			return;
		}

		if (!empty($new_task_owner)) {
			$new_task_owner->setRole(TaskMember::ROLE_OWNER);
			$this->entityManager
				 ->persist($new_task_owner);

			if ($entity->getType() == "kanbanizetask") {
				$this->updateAssigneeOnKanbanize($entity);
			}

			return;
		}

		$newOwner = $this->entityManager
						 ->find(User::class, $new_owner_id);

		$addedBy = $this->entityManager
						->find(User::class, $by_id);

		$entity->addMember($newOwner, TaskMember::ROLE_OWNER, $addedBy, $event->occurredOn())
			->setMostRecentEditAt($event->occurredOn())
			->setMostRecentEditBy($addedBy);

		$this->entityManager
			 ->persist($entity);

		if ($entity->getType() == "kanbanizetask") {
			$this->updateAssigneeOnKanbanize($entity);
		}
	}

	protected function onOwnerRemoved(StreamEvent $event) {

		$id = $event->metadata()['aggregate_id'];
		$ex_owner_id = $event->payload()['ex_owner'];
		$by = $event->payload()['by'];

		if ($ex_owner_id == $by) {
			return;
		}

		$ex_owner = $this->entityManager
			->find(User::class, $ex_owner_id);

		if (!$ex_owner) {
			return;
		}

		$removedBy = $this->entityManager
			->find(User::class, $by);


		$entity = $this->entityManager->find(Task::class, $id);

		$member = $entity->getMember($ex_owner);
		$member->setRole(TaskMember::ROLE_MEMBER);

		$entity->setMostRecentEditAt($event->occurredOn())
			   ->setMostRecentEditBy($removedBy);

		$this->entityManager
			 ->persist($member);

	 	$this->entityManager
			 ->persist($entity);
	}

	protected function getPackage() {
		return 'TaskManagement';
	}

	private function updateAssigneeOnKanbanize($task)
	{
        try {

            $org = $this->orgService
					->findOrganization($task->getOrganizationId());

            $settings = $org->getSettings($this::KANBANIZE_SETTINGS);

            if (is_null($settings) || empty($settings)) {
                return;
            }

            $this->kanbanizeService
                 ->initApi($settings['apiKey'], $settings['accountSubdomain']);

            $boardId = $task->getStream()->getBoardId();
            $taskId = $task->getTaskId();

            $kanbanizeTask = $this->kanbanizeService
                                  ->getTaskDetails($boardId, $taskId);

            $this->kanbanizeService
                 ->updateTask(
                        $task,
                        $kanbanizeTask,
                        $boardId
            );

        } catch (\Exception $e) {
            error_log($e->getMessage() . "\n" .  $e->getTraceAsString());
        }

	}

	private function updateOnKanbanize($task)
    {
        try {

            $kanbanizeStream = $task->getStream();
            $kanbanizeBoardId = $kanbanizeStream->getBoardId();

            // getting organization
            $org = $this->orgService->findOrganization($task->getOrganizationId());
            $kanbanizeSettings = $org->getSettings($this::KANBANIZE_SETTINGS);

            if (is_null ( $kanbanizeSettings ) || empty ( $kanbanizeSettings )) {
                return;
            }

            // Init KanbanizeAPI on kanbanizeService
            $this->kanbanizeService->initApi ( $kanbanizeSettings ['apiKey'], $kanbanizeSettings ['accountSubdomain'] );
            $this->kanbanizeService->loadLanesFromKanbanize($kanbanizeBoardId);

            $mapping = $kanbanizeSettings ['boards'] [$kanbanizeBoardId] ['columnMapping'];

            $key = array_search($task->getStatus(), $mapping);

            $this->kanbanizeService
                 ->moveTaskonKanbanize($task, $key, $kanbanizeBoardId);

        } catch (\Exception $e) {
            error_log($e->getMessage() . "\n" .  $e->getTraceAsString());
        }
	}

	private function deleteOnKanbanize($task)
    {
        try {

            $kanbanizeStream = $task->getStream();
            $kanbanizeBoardId = $kanbanizeStream->getBoardId();

            $org = $this->orgService->findOrganization($task->getOrganizationId());
            $kanbanizeSettings = $org->getSettings($this::KANBANIZE_SETTINGS);

            if ($kanbanizeSettings === null || empty($kanbanizeSettings)) {
                return;
            }

            $this->kanbanizeService
                 ->initApi($kanbanizeSettings['apiKey'], $kanbanizeSettings ['accountSubdomain']);

            $this->kanbanizeService
                ->deleteTask($task, $kanbanizeBoardId);

        } catch (\Exception $e) {
            error_log($e->getMessage() . "\n" .  $e->getTraceAsString());
        }

	}
}
