<?php
namespace TaskManagement\Service;

use Application\Entity\User;
use Application\Service\ReadModelProjector;
use Kanbanize\Entity\KanbanizeTask;
use Prooph\EventStore\Stream\StreamEvent;
use TaskManagement\Entity\Estimation;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\Task;
use TaskManagement\Entity\TaskMember;

class TaskCommandsListener extends ReadModelProjector
{
	protected function onTaskCreated(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$stream = $this->entityManager->find(Stream::class, $event->payload()['streamId']);
		if(is_null($stream)) {
			return;
		}

		$createdBy = $this->entityManager->find(User::class, $event->payload()['by']);
		
		switch($event->metadata()['aggregate_type']) {
			case KanbanizeTask::class :
				$entity = new KanbanizeTask($id, $stream);
				$entity->setBoardId($event->payload()['kanbanizeBoardId']);
				$entity->setTaskId($event->payload()['kanbanizeTaskId']);
				break;
			default:
				$entity = new Task($id, $stream);
		}
		$entity->setStatus($event->payload()['status'])
			   ->setCreatedAt($event->occurredOn())
			   ->setCreatedBy($createdBy)
			   ->setMostRecentEditAt($event->occurredOn())
			   ->setMostRecentEditBy($createdBy);
		$this->entityManager->persist($entity);
	}
	
	protected function onTaskUpdated(StreamEvent $event) {
		if(isset($event->payload()['subject'])) {
			$id = $event->metadata()['aggregate_id'];
			$entity = $this->entityManager->find(Task::class, $id);
			if(is_null($entity)) {
				return;
			}
			$updatedBy = $this->entityManager->find(User::class, $event->payload()['by']);

			$entity->setSubject($event->payload()['subject']);
			$entity->setMostRecentEditAt($event->occurredOn());
			$entity->setMostRecentEditBy($updatedBy);
			$this->entityManager->persist($entity);
		}
	}
	
	protected function onTaskStreamChanged(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(Task::class, $id);
		if(is_null($entity)) {
			return;
		}
		$streamId = $event->payload()['streamId'];
		$stream = $this->entityManager->find(Stream::class, $streamId);
		if(is_null($stream)) {
			return;
		}
		$updatedBy = $this->entityManager->find(User::class, $event->payload()['by']);

		$entity->setStream($stream);
		$entity->setMostRecentEditAt($event->occurredOn());
		$entity->setMostRecentEditBy($updatedBy);
		$this->entityManager->persist($entity);
	}

	protected function onTaskMemberAdded(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(Task::class, $id);
		$user = $this->entityManager->find(User::class, $event->payload()['userId']);
		$addedBy = $this->entityManager->find(User::class, $event->payload()['by']);
		$role = $event->payload()['role'];
		$entity->addMember($user, $role, $addedBy, $event->occurredOn())
			->setMostRecentEditAt($event->occurredOn())
			->setMostRecentEditBy($addedBy);
		$this->entityManager->persist($entity);
	}
	
	protected function onTaskMemberRemoved(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(Task::class, $id);
		$member = $this->entityManager->find(User::class, $event->payload()['userId']);
		$removedBy = $this->entityManager->find(User::class, $event->payload()['by']);
		$entity->removeMember($member)
			->setMostRecentEditAt($event->occurredOn())
			->setMostRecentEditBy($removedBy);
		$this->entityManager->persist($entity);
	}
	
	protected function onTaskDeleted(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(Task::class, $id);
		if(is_null($entity)) {
			return;
		}
		$this->entityManager->remove($entity); // TODO: Solo con l'id no?
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
	
	protected function onTaskCompleted(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$task->setStatus(Task::STATUS_COMPLETED);
		$task->resetShares();
		$user = $this->entityManager->find(User::class, $event->payload()['by']);
		$task->setMostRecentEditBy($user);
		$task->setMostRecentEditAt($event->occurredOn());
		$task->resetAcceptedAt();
		$this->entityManager->persist($task);
	}
	
	protected function onTaskAccepted(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$task->setStatus(Task::STATUS_ACCEPTED);
		$user = $this->entityManager->find(User::class, $event->payload()['by']);
		$task->setMostRecentEditBy($user);
		$task->setMostRecentEditAt($event->occurredOn());
		$task->setAcceptedAt($event->occurredOn());
		$sharesAssignmentExpiresAt = clone $event->occurredOn();
		$sharesAssignmentExpiresAt->add($event->payload()['intervalForCloseTask']);
		$task->setSharesAssignmentExpiresAt($sharesAssignmentExpiresAt);
		$this->entityManager->persist($task);
	}
	
	protected function onTaskClosed(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$task->setStatus(Task::STATUS_CLOSED);
		$user = $this->entityManager->find(User::class, $event->payload()['by']);
		$task->setMostRecentEditBy($user);
		$task->setMostRecentEditAt($event->occurredOn());
		$this->entityManager->persist($task);
	}
	
	protected function onTaskOngoing(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$task->setStatus(Task::STATUS_ONGOING);
		$user = $this->entityManager->find(User::class, $event->payload()['by']);
		$task->setMostRecentEditBy($user);
		$task->setMostRecentEditAt($event->occurredOn());
		$this->entityManager->persist($task);
	}
	
	protected function onSharesAssigned(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$evaluator = $task->getMember($event->payload()['by']);
		$evaluator->resetShares();
		$shares = $event->payload()['shares'];
		foreach($shares as $key => $value) {
			$valued = $task->getMember($key);
			$evaluator->assignShare($valued, $value, $event->occurredOn());
		}
		$this->entityManager->persist($task);
	}
	
	protected function onSharesSkipped(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$evaluator = $task->getMember($event->payload()['by']);
		$evaluator->resetShares();
		foreach($task->getMembers() as $valued) {
			$evaluator->assignShare($valued, null, $event->occurredOn());
		}
		$this->entityManager->persist($task);
	}

	protected function onCreditsAssigned(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$task = $this->entityManager->find(Task::class, $id);
		$credits = $event->payload()['credits'];
		foreach($task->getMembers() as $member) {
			$member->setCredits($credits[$member->getUser()->getId()]);
			$this->entityManager->persist($member);
		}
	}

	protected function getPackage() {
		return 'TaskManagement';
	}
}