<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;

/**
 * @ORM\Entity
 */
class CreditsSubtractedCard extends FlowCard {

	public function serialize() {

		$type = FlowCardInterface::CREDITS_SUBTRACTED_CARD;
		$content = $this->getContent();

        $rv = [];
        $rv['type'] = $type;
        $rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
        $rv['id'] = $this->getId();
        $rv['title'] = "{$content[$type]['amount']} credits subtracted from your account";
        $rv['content'] = [
            'description' => "The user {$content[$type]['userName']} subtracted {$content[$type]['amount']} credits from your account",
            'actions' => [
                'primary' => [],
                'secondary' => []
            ],
        ];

        return $rv;
	}
}