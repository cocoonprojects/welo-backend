<?php

namespace TaskManagement\Processor;

use Application\DomainEvent;
use Application\Entity\User;
use Application\Service\Processor;
use Application\Service\UserService;
use Doctrine\ORM\EntityManager;
use People\Service\OrganizationService;
use Prooph\EventStore\EventStore;
use TaskManagement\Event\TaskUpdated;
use TaskManagement\Service\TaskService;
use TaskManagement\TaskInterface;
use TaskManagement\TaskOngoing;
use Zend\EventManager\Event;

class UpdateItemPositionProcessor extends Processor
{
    protected $organizationService;

    protected $userService;

    protected $taskService;

    protected $entityManager;

    protected $eventStore;

    public function __construct(OrganizationService $organizationService, UserService $userService, TaskService $taskService, EntityManager $em, EventStore $es)
    {
        $this->organizationService = $organizationService;
        $this->userService = $userService;
        $this->taskService = $taskService;
        $this->entityManager = $em;
        $this->eventStore = $es;
    }

    public function getRegisteredEvents()
    {
        return [
            TaskUpdated::class,
            TaskOngoing::class
        ];
    }

    public function handleTaskUpdated(TaskUpdated $event)
    {
        if (!$event->lane()) {
            return;
        }

        $by = $this->entityManager
                   ->find(User::class, $event->by());

        $task = $this->taskService
                     ->getTask($event->aggregateId());

        $previousPosition = $task->getPosition();

        $this->assignItemPositionInNewLane($task, $event->lane(), $by);
        $this->updatePositionsForItemsInPreviousLane($task->getOrganizationId(), $previousPosition, $event->previousLane(), $by);
    }


    public function handleTaskOngoing(Event $event)
    {
        $streamEvent = $event->getTarget();
        $itemId = $streamEvent->metadata()['aggregate_id'];
        $byId = $event->getParam('by');
        $by = $this->userService->findUser($byId);

        $task = $this->taskService
            ->getTask($itemId);

        $this->updatePositionsForItemsInOpenState($task->getOrganizationId(), $by);
    }


    protected function assignItemPositionInNewLane($task, $lane, $by)
    {
        $position = $this->taskService
            ->getNextOpenTaskPosition($task->getId(), $task->getOrganizationId(), $lane);

        $this->eventStore->beginTransaction();

        try {

            $task->setPosition($position, $by);
            $this->eventStore->commit();

        } catch (\Exception $e) {
            $this->eventStore->rollback();
            throw $e;
        }
    }


    protected function updatePositionsForItemsInOpenState($organizationId, $by)
    {
        $organization = $this->organizationService->getOrganization($organizationId);

        $tasksReadModel = $this->taskService
            ->findTasks($organization, 0, 99999, ['status' => TaskInterface::STATUS_OPEN], ['orderBy' => 'priority', 'orderType' => 'ASC']);

        $this->updatePositionsForItems($tasksReadModel, 1, $by);
    }


    protected function updatePositionsForItemsInPreviousLane($organizationId, $previousPosition, $previousLane, $by)
    {
        $tasksReadModel = $this->taskService
                               ->findTasksInLaneAfter($organizationId, $previousLane, $previousPosition);

        $this->updatePositionsForItems($tasksReadModel, $previousPosition, $by);
    }


    protected function updatePositionsForItems($items, $firstPosition, $by)
    {
        foreach ($items as $taskReadModel) {

            $this->eventStore->beginTransaction();

            try {
                $task = $this->taskService->getTask($taskReadModel->getId());

                $task->setPosition($firstPosition, $by);
                $this->eventStore->commit();

            } catch (\Exception $e) {
                $this->eventStore->rollback();
                throw $e;
            }

            $firstPosition++;
        }
    }
}