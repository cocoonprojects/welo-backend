<?php

namespace FlowManagement\Service;

use Application\Service\UserService;
use Application\Service\ReadModelProjector;
use FlowManagement\Entity\ItemMemberAddedCard;
use Prooph\EventStore\Stream\StreamEvent;
use Application\Entity\User;
use FlowManagement\Entity\WelcomeCard;
use FlowManagement\Entity\VoteIdeaCard;
use FlowManagement\Entity\VoteCompletedItemCard;
use FlowManagement\Entity\VoteCompletedItemVotingClosedCard;
use FlowManagement\Entity\VoteCompletedItemReopenedCard;
use FlowManagement\Entity\ItemOwnerChangedCard;
use FlowManagement\Entity\ItemMemberRemovedCard;
use FlowManagement\Entity\OrganizationMemberRoleChangedCard;
use FlowManagement\FlowCardInterface;
use TaskManagement\Entity\Task;
use FlowManagement\Entity\FlowCard;

class CardCommandsListener extends ReadModelProjector {

	public function onFlowCardCreated(StreamEvent $event)
	{
		$createdBy = $this->entityManager
			->find(User::class, $event->payload()['by']);

		$recipient = $this->entityManager
			->find(User::class, $event->payload()['to']);

		$entity = $this->cardFactory($recipient, $event);

		if (!is_null($entity)) {
			$entity->setCreatedAt($event->occurredOn());
			$entity->setCreatedBy($createdBy);
			$entity->setMostRecentEditAt($event->occurredOn());
			$entity->setMostRecentEditBy($createdBy);
			$this->entityManager->persist($entity);
			$this->entityManager->flush($entity);
		}
	}

	public function onFlowCardHidden(StreamEvent $event) {
		$id = $event->metadata()['aggregate_id'];
		$entity = $this->entityManager->find(FlowCard::class, $id);
		if(!is_null($entity)){
			$entity->setMostRecentEditAt($event->occurredOn());
			$entity->hide();
			$this->entityManager->persist($entity);
			$this->entityManager->flush($entity);
		}
	}

	private function cardFactory(User $recipient, StreamEvent $event){

	    $id = $event->metadata()['aggregate_id'];
		$content = $event->payload()['content'];
		$type = $event->metadata()['aggregate_type'];

		switch ($type){
			case 'FlowManagement\VoteIdeaCard':
				$entity = new VoteIdeaCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::VOTE_IDEA_CARD, $content);
				break;
			case 'FlowManagement\VoteCompletedItemCard':
				$entity = new VoteCompletedItemCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::VOTE_COMPLETED_ITEM_CARD, $content);
				break;
			case 'FlowManagement\VoteCompletedItemVotingClosedCard':
				$entity = new VoteCompletedItemVotingClosedCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::VOTE_COMPLETED_ITEM_VOTING_CLOSED_CARD, $content);
				break;
			case 'FlowManagement\VoteCompletedItemReopenedCard':
				$entity = new VoteCompletedItemReopenedCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::VOTE_COMPLETED_ITEM_REOPENED_CARD, $content);
				break;
			case 'FlowManagement\ItemOwnerChangedCard':
				$entity = new ItemOwnerChangedCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::ITEM_OWNER_CHANGED_CARD, $content);
				break;
			case 'FlowManagement\ItemMemberAddedCard':
				$entity = new ItemMemberAddedCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::ITEM_MEMBER_ADDED_CARD, $content);
				break;
			case 'FlowManagement\ItemMemberRemovedCard':
				$entity = new ItemMemberRemovedCard($id, $recipient);
				$item = $this->entityManager->find(Task::class, $event->payload()['item']);
				if(!is_null($item)){
					$entity->setItem($item);
				}
				$entity->setContent(FlowCardInterface::ITEM_MEMBER_REMOVED_CARD, $content);
				break;
			case 'FlowManagement\OrganizationMemberRoleChangedCard':

				$entity = OrganizationMemberRoleChangedCard::create($id, $recipient, $content);

				break;
			default:
				$entity = null;
		}

		return $entity;
	}

	protected function getPackage() {
		return 'FlowManagement';
	}
}