<?php

namespace TaskManagement\Processor;

use Application\Entity\User;
use Application\Service\Processor;
use Doctrine\ORM\EntityManager;
use Prooph\EventStore\EventStore;
use TaskManagement\Event\TaskUpdated;
use TaskManagement\Service\TaskService;

class UpdateItemPositionProcessor extends Processor
{
    protected $taskService;

    protected $entityManager;

    protected $eventStore;

    public function __construct(TaskService $taskService, EntityManager $em, EventStore $es)
    {
        $this->taskService = $taskService;
        $this->entityManager = $em;
        $this->eventStore = $es;
    }

    public function getRegisteredEvents()
    {
        return [
            TaskUpdated::class
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

    protected function updatePositionsForItemsInPreviousLane($organizationId, $previousPosition, $previousLane, $by)
    {
        $tasksReadModel = $this->taskService
                               ->findTasksInLaneAfter($organizationId, $previousLane, $previousPosition);

        $updatedPosition = $previousPosition;

        foreach ($tasksReadModel as $taskReadModel) {

            $this->eventStore->beginTransaction();

            try {
                $task = $this->taskService->getTask($taskReadModel->getId());

                $task->setPosition($updatedPosition, $by);
                $this->eventStore->commit();

            } catch (\Exception $e) {
                $this->eventStore->rollback();
                throw $e;
            }

            $updatedPosition++;
        }
    }
}