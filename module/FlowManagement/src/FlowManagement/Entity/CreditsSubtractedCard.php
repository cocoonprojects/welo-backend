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
class CreditsSubtractedCard extends FlowCard
{
	public function serialize()
    {
		$type = FlowCardInterface::CREDITS_SUBTRACTED_CARD;
		$content = $this->getContent()[$type];

        $rv = [];
        $rv['type'] = $type;
        $rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
        $rv['id'] = $this->getId();
        $rv['title'] = "{$content['amount']} credits subtracted from your account";
        $rv['content'] = [
            'description' => "The user {$content['userName']} subtracted {$content['amount']} credits from your account",
            'actions' => [
                'primary' => [
                    'text' => 'See your balance',
                    'orgId' => isset($content['orgId']) ? $content['orgId'] : null,
                    'userId' => isset($content['userId']) ? $content['userId'] : null
                ],
            ],
        ];

        return $rv;
	}

	public static function create(Uuid $uuid, $amount, User $payer, Organization $org, User $by)
    {
        $data = [
            'userName'  => $by->getDislayedName(),
            'userId'    => $by->getId(),
            'orgName'   => $org->getName(),
            'orgId'     => $org->getId(),
            'amount'    => abs($amount),
        ];

        $flowCard = new static($uuid, $payer);
        $flowCard->setContent(FlowCardInterface::CREDITS_SUBTRACTED_CARD, $data);
        $flowCard->setCreatedBy($by);

        return $flowCard;
    }

}