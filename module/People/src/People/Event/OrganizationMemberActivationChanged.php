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

    protected $organizationId;

    protected $active;

    protected $by;

    public static function happened($aggregateId, Uuid $organizationId, Uuid $userId, $active, BasicUser $by)
    {
        $event = self::occur($aggregateId, ['userId' => $userId, 'organizationId' => $organizationId, 'active' => $active, 'by' => $by]);
        $event->userId = $userId->toString();
        $event->organizationId = $organizationId->toString();
        $event->active = $active;
        $event->by = $by->getId();

        return $event;
    }

    public function userId()
    {
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