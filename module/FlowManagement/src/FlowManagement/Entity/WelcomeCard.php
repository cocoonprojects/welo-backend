<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class WelcomeCard extends FlowCard
{
	public function serialize()
    {
	    $type = FlowCardInterface::WELCOME_CARD;
		$content = $this->getContent()[$type];

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "Welcome to ''";
		$rv['content'] = [
			'description' => $content['text'],
			'actions' => [
			],
		];

		return $rv;
	}
}