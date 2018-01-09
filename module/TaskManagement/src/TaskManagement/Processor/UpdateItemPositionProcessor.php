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

        $position = $this->taskService
                         ->getNextOpenTaskPosition($task->getOrganizationId(), $event->lane());


        $this->eventStore->beginTransaction();

        try{

            $task->setPosition($position, $by);
            $this->eventStore->commit();

        } catch (\Exception $e) {
            $this->eventStore->rollback();
            throw $e;
        }

    }

}