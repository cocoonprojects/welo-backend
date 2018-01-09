<?php
namespace Kanbanize\Service;

use Application\Entity\User;
use Application\Service\ReadModelProjector;
use Doctrine\ORM\EntityManager;
use Kanbanize\KanbanizeTask;
use Kanbanize\Entity\KanbanizeTask as ReadModelKanbanizeTask;
use TaskManagement\Entity\Stream;
use TaskManagement\Event\TaskUpdated;
use TaskManagement\Service\TaskService;
use TaskManagement\TaskCreated;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Application;

class TaskCommandsListener extends ReadModelProjector {

	private $taskService;

	public function __construct(EntityManager $entityManager, TaskService $taskService)
    {
		parent::__construct($entityManager);

		$this->taskService = $taskService;
	}

	public function attach(EventManagerInterface $events){
		parent::attach($events);

		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskCreated::class,
			function(EventInterface $event) {
				$streamEvent = $event->getTarget();
				$id = $streamEvent->metadata()['aggregate_id'];
				if($streamEvent->metadata()['aggregate_type'] == KanbanizeTask::class){
					$stream = $this->entityManager->find(Stream::class, $streamEvent->payload()['streamId']);
					if(is_null($stream)) {
						return;
					}
					$createdBy = $this->entityManager->find(User::class, $streamEvent->payload()['by']);

					$entity = new ReadModelKanbanizeTask($id, $stream, $streamEvent->payload()['decision']);

					$entity->setTaskId($streamEvent->payload()['taskid'])
						->setSubject($streamEvent->payload()['subject'])
						->setColumnName($streamEvent->payload()['columnname'])
						->setLane($streamEvent->payload()['lane'])
						->setStatus($streamEvent->payload()['status'])
						->setCreatedAt($streamEvent->occurredOn())
						->setCreatedBy($createdBy)
						->setMostRecentEditAt($streamEvent->occurredOn())
						->setMostRecentEditBy($createdBy);
					$this->entityManager->persist($entity);
					$this->entityManager->flush($entity);
				}
		}, 200);

		$this->listeners[] = $events->getSharedManager()->attach(Application::class, TaskUpdated::class,
			function(TaskUpdated $event) {

                $entity = $this->taskService->findTask($event->aggregateId());

				if(!is_a($entity, ReadModelKanbanizeTask::class)) {
                    return;
				}

				$by = $this->entityManager->find(User::class, $event->by());

                $entity = $this->updateEntity($entity, $by, $event);

                $this->entityManager->persist($entity);
                $this->entityManager->flush($entity);

		    }, 200
        );
    }

	private function updateEntity(ReadModelKanbanizeTask $task, User $updatedBy, $streamEvent) {
		if (isset ( $streamEvent->payload ()['taskid'] )) {
			$task->setTaskId ( $streamEvent->payload ()['taskid'] );
		}
		if (isset ( $streamEvent->payload ()['columnname'] )) {
			$task->setColumnName ( $streamEvent->payload ()['columnname'] );
		}
		if (isset ( $streamEvent->payload ()['assignee'] )) {
			$task->setAssignee ( $streamEvent->payload ()['assignee'] );
		}
		if(isset($streamEvent->payload()['lane'])) {
			$task->setLane($streamEvent->payload()['lane']);
		}
		if(isset($streamEvent->payload()['position'])) {
			$task->setPosition($streamEvent->payload()['position']);
		}
		if(isset($streamEvent->payload()['assignee'])) {
			$task->setAssignee($streamEvent->payload()['assignee']);
		}
		if (isset ( $streamEvent->payload ()['subject'] )) {
			$task->setSubject ( $streamEvent->payload ()['subject'] );
		}
		if (isset ( $streamEvent->payload ()['description'] )) {
			$task->setDescription( $streamEvent->payload ()['description'] );
		}
		$task->setMostRecentEditAt ( $streamEvent->occurredOn () );
		$task->setMostRecentEditBy ( $updatedBy );

		return $task;
	}
	protected function getPackage() {
		return 'Kanbanize';
	}

	public function detach(EventManagerInterface $events) {
		parent::detach ( $events );
		foreach ( $this->listeners as $index => $listener ) {
			if ($events->getSharedManager()->detach(Application::class, $listener[$index])) {
				unset($this->listeners[$index]);
			}
		}
	}
}