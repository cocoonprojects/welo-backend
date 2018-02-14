<?php

namespace TaskManagement\Projector;

use Application\Entity\User;
use Application\Service\Projector;
use TaskManagement\Entity\Task;
use TaskManagement\Event\TaskPositionUpdated;
use TaskManagement\Event\TaskRevertedToAccepted;
use TaskManagement\Event\TaskRevertedToCompleted;
use TaskManagement\TaskInterface;
use People\Event\OrganizationMemberRemoved;
use TaskManagement\Event\TaskMemberRemoved;

class TaskProjector extends Projector
{
    public function getRegisteredEvents()
    {
        return [
            TaskPositionUpdated::class,
            TaskRevertedToCompleted::class,
            TaskRevertedToAccepted::class,
            TaskMemberRemoved::class
        ];
    }

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

    public function applyTaskRevertedToCompleted(TaskRevertedToCompleted $event)
    {
        $task = $this->entityManager
                     ->find(Task::class, $event->aggregateId());

        $task->setStatus(TaskInterface::STATUS_COMPLETED);
        $task->removeAcceptances();
        $task->resetShares();

        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function applyTaskRevertedToAccepted(TaskRevertedToAccepted $event)
    {
        $task = $this->entityManager
            ->find(Task::class, $event->aggregateId());

        $task->setStatus(TaskInterface::STATUS_ACCEPTED);
        $task->resetCredits();
        $task->resetShares();

        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }
}