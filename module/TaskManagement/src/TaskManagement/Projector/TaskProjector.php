<?php

namespace TaskManagement\Projector;

use Application\Entity\User;
use Application\Service\Projector;
use TaskManagement\Entity\Task;
use TaskManagement\Event\TaskPositionUpdated;

class TaskProjector extends Projector
{
    public function applyTaskPositionUpdated(TaskPositionUpdated $event)
    {
        $task = $this->entityManager
                     ->find(Task::class, $event->aggregateId());

        $user = $this->entityManager
                     ->find(User::class, $event->by());

        $task->updatePosition($event->position(), $user, $event->occurredOn());

        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function getRegisteredEvents()
    {
        return [
            TaskPositionUpdated::class
        ];
    }
}