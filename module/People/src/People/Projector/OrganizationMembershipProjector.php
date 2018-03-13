<?php

namespace People\Projector;

use Application\Entity\User;
use Application\Service\Projector;
use People\Entity\Organization;
use People\Entity\OrganizationMembership;
use People\Event\LaneAdded;
use People\Event\LaneDeleted;
use People\Event\LaneUpdated;
use People\Event\OrganizationMemberActivationChanged;

class OrganizationMembershipProjector extends Projector
{
    public function getRegisteredEvents()
    {
        return [
            OrganizationMemberActivationChanged::class
        ];
    }

    public function applyOrganizationMemberActivationChanged(OrganizationMemberActivationChanged $event)
    {
        $membershipReadModel = $this->entityManager
                             ->find(OrganizationMembership::class, ['member' => $event->userId(), 'organization' => $event->organizationId()]);

        $membershipReadModel->setActive($event->active());

        $this->entityManager->persist($membershipReadModel);
        $this->entityManager->flush();
    }
}