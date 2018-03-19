<?php

namespace People\Event;

use Application\DomainEvent;
use Application\Entity\BasicUser;
use People\Entity\Organization;
use Prooph\EventSourcing\AggregateChanged;
use Rhumsaa\Uuid\Uuid;

class OrganizationMemberActivationChanged extends DomainEvent
{
    protected $userId;
    protected $userName;
    protected $organizationId;
    protected $active;
    protected $by;

    public static function happened($aggregateId, Uuid $organizationId, BasicUser $user, $active, BasicUser $by)
    {
        $name = $user->getFirstname().' '.$user->getLastname();

        $event = self::occur($aggregateId, [
            'userId' => $user->getId(),
            'userName' => $name,
            'organizationId' => $organizationId->toString(),
            'active' => $active,
            'by' => $by->getId()
        ]);

        $event->userId = $user->getId();
        $event->userName = $name;
        $event->organizationId = $organizationId;
        $event->active = $active;
        $event->by = $by;

        return $event;
    }

    public function userId()
    {
        if (!$this->userId) {
            return null;
        }
        return is_string($this->userId) ? $this->userId : $this->userId->toString();
    }

    public function organizationId()
    {
        return $this->organizationId;
    }

    public function active()
    {
        return $this->active;
    }

    public function by()
    {
        return $this->by;
    }
}