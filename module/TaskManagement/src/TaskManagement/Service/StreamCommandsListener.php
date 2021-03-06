<?php
namespace TaskManagement\Service;

use Prooph\EventStore\Stream\StreamEvent;
use People\Entity\Organization;
use Application\Entity\User;
use Application\Service\ReadModelProjector;
use TaskManagement\Stream as WriteModelStream;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\Task;

class StreamCommandsListener extends ReadModelProjector {

	protected function onStreamCreated(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$type = $event->metadata()['aggregate_type'];
		if($type == WriteModelStream::class){
			$organizationId = $event->payload()['organizationId'];
			$organization = $this->entityManager->find(Organization::class, $organizationId);
			if(is_null($organization)) {
				return;
			}
			$createdBy = $this->entityManager->find(User::class, $event->payload()['by']);
			$stream = new Stream($id, $organization);
			$stream->setCreatedAt($event->occurredOn())
				->setCreatedBy($createdBy)
				->setMostRecentEditAt($event->occurredOn())
				->setMostRecentEditBy($createdBy);

			$this->entityManager->persist($stream);
		}
		return;
	}

	protected function onStreamUpdated(StreamEvent $event) {

		$id = $event->metadata()['aggregate_id'];
		$stream = $this->entityManager->find(Stream::class, $id);

		if(is_null($stream)) {
			return;
		}

		$updatedBy = $this->entityManager
			->find(User::class, $event->payload()['by']);

		if (isset($event->payload()['subject'])) {
			$stream->setSubject($event->payload()['subject']);
		}

		if (array_key_exists('boardId', $event->payload())) {
			$stream->setBoardId($event->payload()['boardId']);
		}

		$stream->setMostRecentEditAt($event->occurredOn());
		$stream->setMostRecentEditBy($updatedBy);

		$this->entityManager->persist($stream);
	}

	protected function onStreamOrganizationChanged(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(Task::class, $id);
		if(is_null($entity)) {
			return;
		}
		$organizationId = $event->payload()['organizationId'];
		$organization = $this->entityManager->find(Organization::class, $organizationId);
		if(is_null($organization)) {
			return;
		}
		$updatedBy = $this->entityManager->find(User::class, $event->payload()['by']);

		$entity->setOrganization($organization);
		$entity->setMostRecentEditAt($event->occurredOn());
		$entity->setMostRecentEditBy($updatedBy);
		$this->entityManager->persist($entity);
	}

	protected function getPackage() {
		return 'TaskManagement';
	}
}