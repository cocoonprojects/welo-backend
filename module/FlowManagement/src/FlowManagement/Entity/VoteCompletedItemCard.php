<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use Application\Service\FrontendRouter;

/**
 * @ORM\Entity
 */
class VoteCompletedItemCard extends FlowCard {
	
	public function serialize(FrontendRouter $feRouter){
		$rv = [];
		$type = FlowCardInterface::VOTE_COMPLETED_ITEM_CARD;
		$content = $this->getContent();
		$rv["type"] = $type;
		$rv["createdAt"] = date_format($this->getCreatedAt(), 'c');
		$rv["id"] = $this->getId();
		$rv["title"] = "Completed item '".$this->getItem()->getSubject()."' needs to be accepted";
		$rv["content"] = [
			"description" => $this->getItem()->getDescription(),
			"actions" => [
				"primary" => [
					"text" => "Do you want this completed work item to be accepted?",
					"orgId" => $content[$type]["orgId"],
					"itemId" => $this->getItem()->getId()
				],
				"secondary" => []
			],
		];
		return $rv;
	}
}