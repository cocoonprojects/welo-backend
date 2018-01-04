<?php

namespace People\Projector;

use Application\Entity\User;
use Application\Service\Projector;
use People\Entity\Organization;
use People\Event\LaneAdded;
use People\Event\LaneDeleted;
use People\Event\LaneUpdated;

class OrganizationProjector extends Projector
{
    public function getRegisteredEvents()
    {
        return [
            LaneAdded::class,
            LaneUpdated::class,
            LaneDeleted::class
        ];
    }

    public function applyLaneAdded(LaneAdded $event)
    {
        $orgReadModel = $this->entityManager
                             ->find(Organization::class, $event->aggregateId());

        $user = $this->entityManager
                     ->find(User::class, $event->by());

        $orgReadModel->addLane($event->id(), $event->name(), $user, $event->occurredOn());

        $this->entityManager->persist($orgReadModel);
        $this->entityManager->flush();
    }

    public function applyLaneUpdated(LaneUpdated $event)
    {
        $orgReadModel = $this->entityManager
                             ->find(Organization::class, $event->aggregateId());

        $user = $this->entityManager
                     ->find(User::class, $event->by());

        $orgReadModel->updateLane($event->id(), $event->name(), $user, $event->occurredOn());

        $this->entityManager->persist($orgReadModel);
        $this->entityManager->flush();
    }

    public function applyLaneDeleted(LaneDeleted $event)
    {
        $orgReadModel = $this->entityManager
                             ->find(Organization::class, $event->aggregateId());

        $user = $this->entityManager
                     ->find(User::class, $event->by());

        $orgReadModel->deleteLane($event->id(), $user, $event->occurredOn());

        $this->entityManager->persist($orgReadModel);
        $this->entityManager->flush();
    }
}