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

class TaskProjector extends Projector
{
    public function getRegisteredEvents()
    {
        return [
            TaskPositionUpdated::class,
            TaskRevertedToCompleted::class,
            TaskRevertedToAccepted::class,
            OrganizationMemberRemoved::class
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

    public function applyOrganizationMemberRemoved(OrganizationMemberRemoved $event)
    {
        $orgId = $event->aggregateId();
        $userId = $event->userId();

        $builder = $this->entityManager->createQueryBuilder();
        $query = $builder
            ->select('t')
            ->from(Task::class, 't')
            ->innerJoin('t.members', 'm', 'WITH', 'm.user = :userId')
            ->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
            ->where('t.status < :taskStatus')
            ->setParameter('taskStatus', Task::STATUS_CLOSED)
            ->setParameter('userId', $userId)
            ->setParameter('organization', $orgId)
            ->getQuery()
        ;

        $tasks = $query->getResult();

        array_map(function($task) use ($event) {
            $task->removeMember($event->userId());
            $this->entityManager->persist($task);
        }, $tasks);

        $this->entityManager->flush();
    }
}