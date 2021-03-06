<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class VoteCompletedItemReopenedCard extends FlowCard
{
	public function serialize()
    {
		$type = FlowCardInterface::VOTE_COMPLETED_ITEM_REOPENED_CARD;
		$content = $this->getContent()[$type];

        $rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Completed item '{$this->getItem()->getSubject()}' has been rejected";
		$rv['content'] = [
			'description' => "{$this->getItem()->getDescription()}. The item {$this->getItem()->getId()} has not been accepted as completed.",
			'actions' => [
				'primary' => [
					'text' => 'Read More Here !',
					'orgId' => $content['orgId'],
					'itemId' => $this->getItem()->getId()
				],
			],
		];
		return $rv;
	}
}