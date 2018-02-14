<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class ItemOwnerChangedCard extends FlowCard {
	
	public function serialize()
    {
        $type = FlowCardInterface::ITEM_OWNER_CHANGED_CARD;
        $content = $this->getContent()[$type];
        $item = $this->getItem();
        $owner = $item->getOwner() ? $item->getOwner()->getMember() : null;
        $name = $owner ? $owner->getFirstname().' '.$owner->getLastname() : 'nobody';

        $rv = [];
        $rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Owner changed for '".$item->getSubject()."'";
		$rv['content'] = [
			'description' => "The new Item owner is $name",
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