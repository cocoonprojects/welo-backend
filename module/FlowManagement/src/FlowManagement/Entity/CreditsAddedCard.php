<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class CreditsAddedCard extends FlowCard {

	public function serialize() {

		$type = FlowCardInterface::CREDITS_ADDED_CARD;
		$content = $this->getContent();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "{$content[$type]['amount']} credits added to your account";
		$rv['content'] = [
			'description' => "The user {$content[$type]['userName']} took these credits from '{$content[$type]['orgName']}' account",
			'actions' => [
				'primary' => [],
				'secondary' => []
			],
		];

		return $rv;
	}
}