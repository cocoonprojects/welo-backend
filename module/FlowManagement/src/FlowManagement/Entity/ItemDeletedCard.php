<?php

namespace FlowManagement\Entity;

use Application\Entity\User;
use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use Rhumsaa\Uuid\Uuid;

/**
 * @ORM\Entity
 */
class ItemDeletedCard extends FlowCard
{
	public function serialize()
    {
	    $type = FlowCardInterface::ITEM_DELETED_CARD;
		$content = $this->getContent()[$type];
        $by = $this->getCreatedBy();

		$rv = [];
		$rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = 'Item deleted';
		$rv['content'] = [
			'description' => "The item '{$content['subject']}' was deleted by {$by->getDislayedName()}",
			'actions' => [
			],
		];

		return $rv;
	}

    public static function create(Uuid $uuid, $subject, User $to, User $by)
    {
        $data = [
            'subject' => $subject,
        ];

        $flowCard = new static($uuid, $to);
        $flowCard->setContent(FlowCardInterface::ITEM_DELETED_CARD, $data);
        $flowCard->setCreatedBy($by);

        return $flowCard;
    }

}