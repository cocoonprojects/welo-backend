<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use Application\Service\FrontendRouter;

/**
 * @ORM\Entity
 */
class ItemMemberRemovedCard extends FlowCard {

	public function serialize(FrontendRouter $feRouter) {

		$type = FlowCardInterface::ITEM_MEMBER_REMOVED_CARD;
		$content = $this->getContent();
		$item = $this->getItem();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Member removed from \"{$item->getSubject()}\"";
		$rv['content'] = [
			'description' => "The user {$content[$type]['userName']} is no more a member of this item",
			'actions' => [
				'primary' => [
					'text' => '',
					'orgId' => $content[$type]['orgId'],
					'itemId' => $item->getId()
				],
				'secondary' => []
			],
		];

		return $rv;
	}
}