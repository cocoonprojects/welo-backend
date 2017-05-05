<?php

namespace FlowManagement\Entity;

use Application\Entity\User;
use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use People\Entity\Organization;
use Rhumsaa\Uuid\Uuid;

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
		$rv['title'] = "Welcome to our organization";
		$rv['content'] = [
			'description' => $content['text'],
			'actions' => [
			],
		];

		return $rv;
	}

    public static function create(Uuid $uuid, Organization $org, User $to, $welcomeText)
    {
        $data = [
            'orgId'   => $org->getId(),
            'text'    => $welcomeText
        ];

        $flowCard = new static($uuid, $to);
        $flowCard->setContent(FlowCardInterface::WELCOME_CARD, $data);
        $flowCard->setCreatedBy($to);

        return $flowCard;
    }

}