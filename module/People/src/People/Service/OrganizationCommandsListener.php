<?php
namespace People\Service;

use Prooph\EventStore\Stream\StreamEvent;
use Application\Entity\User;
use People\Entity\Organization;
use People\Entity\OrganizationMembership;
use Application\Service\ReadModelProjector;

class OrganizationCommandsListener extends ReadModelProjector {
	
	protected function onOrganizationCreated(StreamEvent $event)
    {
	    $id = $event->metadata()['aggregate_id'];

		$createdBy = $this->entityManager->find(User::class, $event->payload()['by']);

		$entity = new Organization($id);
		$entity->setCreatedAt($event->occurredOn());
		$entity->setCreatedBy($createdBy);
		$entity->setMostRecentEditAt($event->occurredOn());
		$entity->setMostRecentEditBy($createdBy);

		$this->entityManager->persist($entity);
	}

	protected function onOrganizationUpdated(StreamEvent $event) {

	    $id = $event->metadata()['aggregate_id'];

	    $entity = $this->entityManager->find(Organization::class, $id);

	    if(isset($event->payload()['name'])) {
			$entity->setName($event->payload()['name']);
		}

		if(isset($event->payload()['syncErrorsNotification'])) {
	        $entity->setSyncErrorsNotification($event->payload()['syncErrorsNotification']);
		}

		if(isset($event->payload()['settingKey']) && isset($event->payload()['settingValue'])) {
			$entity->setSettings($event->payload()['settingKey'], $event->payload()['settingValue']);
		}

		if(isset($event->payload()['lanes']) && is_array($event->payload()['lanes'])) {
			$entity->setLanes($event->payload()['lanes']);
		}

		$updatedBy = $this->entityManager->find(User::class, $event->payload()['by']);
		$entity->setMostRecentEditAt($event->occurredOn());
		$entity->setMostRecentEditBy($updatedBy);

		$this->entityManager->persist($entity);
	}
	
	protected function onOrganizationMemberAdded(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$organization = $this->entityManager->find(Organization::class, $id);
		
		$user = $this->entityManager->find(User::class, $event->payload()['userId']);
		$createdBy = $this->entityManager->find(User::class, $event->payload()['by']);

		$m = new OrganizationMembership($user, $organization);
		$m->setRole($event->payload()['role'])
		  ->setCreatedAt($event->occurredOn())
		  ->setCreatedBy($createdBy)
		  ->setMostRecentEditAt($event->occurredOn())
		  ->setMostRecentEditBy($createdBy)
          ->setActive(true)
        ;

		$this->entityManager->persist($m);
	}
	
	protected function onOrganizationMemberRoleChanged(StreamEvent $event) {
		$createdBy = $this->entityManager->find(User::class, $event->payload()['by']);

		$membership = $this->entityManager
			 			   ->getRepository(OrganizationMembership::class)
						   ->findOneBy(array(
						   		'member' => $event->payload()['userId'],
						   		'organization' => $event->metadata()['aggregate_id']
						   ));

		$membership->setRole($event->payload()['newRole'])
		  ->setMostRecentEditAt($event->occurredOn())
		  ->setMostRecentEditBy($createdBy);
		$this->entityManager->persist($membership);
	}
	
	protected function onOrganizationMemberRemoved(StreamEvent $event) {
		
		$membership = $this->entityManager
			 			   ->getRepository(OrganizationMembership::class)
						   ->findOneBy(array(
						   		'member' => $event->payload()['userId'],
						   		'organization' => $event->metadata()['aggregate_id']
						   ));
		$this->entityManager->remove($membership);
	}

	protected function onShiftOutWarning(StreamEvent $event) {

        $membership = $this->entityManager
                           ->getRepository(OrganizationMembership::class)
                           ->findOneBy(array(
                                'member' => $event->payload()['userId'],
                                'organization' => $event->metadata()['aggregate_id']
                           ));

        $membership->notifyShiftOutWarning();

        $this->entityManager->persist($membership);
    }

	protected function onResetShiftOutWarning(StreamEvent $event) {

        $membership = $this->entityManager
                           ->getRepository(OrganizationMembership::class)
                           ->findOneBy(array(
                                'member' => $event->payload()['userId'],
                                'organization' => $event->metadata()['aggregate_id']
                           ));

        $membership->resetShiftOutWarning();

        $this->entityManager->persist($membership);
    }

	protected function getPackage() {
		return 'People';
	}
}