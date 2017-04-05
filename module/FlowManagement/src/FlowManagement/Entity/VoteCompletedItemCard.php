<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class VoteCompletedItemCard extends FlowCard
{
	public function serialize()
    {
	    $type = FlowCardInterface::VOTE_COMPLETED_ITEM_CARD;
		$content = $this->getContent()[$type];

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Completed item '{$this->getItem()->getSubject()}' needs to be accepted";
		$rv['content'] = [
			'description' => $this->getItem()->getDescription(),
			'actions' => [
				'primary' => [
					'text' => 'Do you want this completed work item to be accepted?',
					'orgId' => $content['orgId'],
					'itemId' => $this->getItem()->getId()
				],
			],
		];
		return $rv;
	}
}