<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class VoteIdeaCard extends FlowCard
{
	public function serialize()
    {
	    $type = FlowCardInterface::VOTE_IDEA_CARD;
		$content = $this->getContent()[$type];
		$item = $this->getItem();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "New item idea '{$item->getSubject()}'";
		$rv['content'] = [
			'description' => $item->getDescription(),
			'actions' => [
				'primary' => [
					'text' => 'Do you want this work item idea to be opened?',
					'orgId' => $content['orgId'],
					'itemId' => $item->getId()
				],
			],
		];

		return $rv;
	}
}