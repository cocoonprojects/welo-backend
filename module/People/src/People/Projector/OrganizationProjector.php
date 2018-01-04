<?php

namespace People\Projector;

use Application\Entity\User;
use Application\Service\Projector;
use People\Entity\Organization;
use People\Event\LaneAdded;

class OrganizationProjector extends Projector
{
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

    public function getRegisteredEvents()
    {
        return [
            LaneAdded::class
        ];
    }
}