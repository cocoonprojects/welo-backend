<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class ItemMemberRemovedCard extends FlowCard {

	public function serialize()
    {
		$type = FlowCardInterface::ITEM_MEMBER_REMOVED_CARD;
		$content = $this->getContent()[$type];
		$item = $this->getItem();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Member removed from \"{$item->getSubject()}\"";
		$rv['content'] = [
			'description' => "The user {$content['userName']} is no more a member of this item",
			'actions' => [
				'primary' => [
					'text' => '',
					'orgId' => $content['orgId'],
					'itemId' => $item->getId()
				],
			],
		];

		return $rv;
	}
}