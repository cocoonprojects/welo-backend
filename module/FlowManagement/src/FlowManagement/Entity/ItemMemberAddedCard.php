<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class ItemMemberAddedCard extends FlowCard {

	public function serialize()
    {
		$type = FlowCardInterface::ITEM_MEMBER_ADDED_CARD;
		$content = $this->getContent()[$type];
		$item = $this->getItem();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Member joined in \"{$item->getSubject()}\"";
		$rv['content'] = [
			'description' => "The user {$content['userName']} joined as a member of this item",
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