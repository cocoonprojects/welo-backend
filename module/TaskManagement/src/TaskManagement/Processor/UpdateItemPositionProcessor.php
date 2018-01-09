<?php

namespace TaskManagement\Processor;

use Application\Entity\User;
use Application\Service\Processor;
use Doctrine\ORM\EntityManager;
use TaskManagement\Event\TaskUpdated;
use TaskManagement\Service\TaskService;

class UpdateItemPositionProcessor extends Processor
{
    protected $taskService;

    protected $entityManager;

    public function __construct(TaskService $taskService, EntityManager $em)
    {
        $this->taskService = $taskService;
        $this->entityManager = $em;
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

        $task->setPosition($position, $by);
    }

}