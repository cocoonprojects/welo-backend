<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use Application\Service\FrontendRouter;

/**
 * @ORM\Entity
 */
class ItemClosedCard extends FlowCard {
	
	public function serialize(FrontendRouter $feRouter){
		$rv = [];
		$type = FlowCardInterface::ITEM_CLOSED_CARD;
		$content = $this->getContent();

		$rv["type"] = $type;
		$rv["createdAt"] = date_format($this->getCreatedAt(), 'c');
		$rv["id"] = $this->getId();
		$rv["title"] = "Item '".$this->getItem()->getSubject()."' is closed";
		$rv["content"] = [
			"description" => $this->getItem()->getDescription(),
            "extraData" => [
                'shares' => $this->getItem()->getSharesSummary(),
                'total' => $this->getItem()->getAverageEstimation()
            ],
			"actions" => [
				"primary" => [
					"text" => "See item details",
					"orgId" => $content[$type]["orgId"],
					"itemId" => $this->getItem()->getId()
				],
				"secondary" => []
			],
		];

		return $rv;
	}
}