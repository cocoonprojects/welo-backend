<?php

namespace FlowManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use FlowManagement\FlowCardInterface;
use Application\Entity\User;

/**
 * @ORM\Entity
 */
class OrganizationMemberRoleChangedCard extends FlowCard
{
	public function serialize() {

		$type = FlowCardInterface::ORGANIZATION_MEMBER_ROLE_CHANGED_CARD;
		$content = $this->getContent()[$type];

		$description = "User {$content['userName']} new role is {$content['newRole']} (was {$content['oldRole']})";
        $description .= ". Change performed by {$content['by']}";

        $rv = [];
        $rv['type'] = $type;
		$rv['createdAt'] = date_format($this->getCreatedAt(), 'c');
		$rv['id'] = $this->getId();
		$rv['title'] = "User {$content['userName']} role changed";
		$rv['content'] = [
			'description' => $description,
			'actions' => [
				'primary' => [
					'text'      => '',
					'orgId'     => $content['orgId'],
                    'userId'    => $content['userId']
				],
			],
		];

		return $rv;
	}

	public static function create($id, User $recipient, $content)
    {
        $entity = new static($id, $recipient);
        $entity->setContent(FlowCardInterface::ORGANIZATION_MEMBER_ROLE_CHANGED_CARD, $content);

        return $entity;
    }
}