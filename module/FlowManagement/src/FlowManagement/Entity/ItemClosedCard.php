<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class ItemClosedCard extends FlowCard {
	
	public function serialize()
    {
	    $type = FlowCardInterface::ITEM_CLOSED_CARD;
		$content = $this->getContent()[$type];

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Item {$this->getItem()->getSubject()} is closed";
		$rv['content'] = [
			'description' => $this->getItem()->getDescription(),
            'extraData' => [
                'shares' => $this->getItem()->getSharesSummary(),
                'total' => $this->getItem()->getAverageEstimation()
            ],
			'actions' => [
				'primary' => [
					'text' => 'See item details',
					'orgId' => $content['orgId'],
					'itemId' => $this->getItem()->getId()
				],
			],
		];

		return $rv;
	}
}