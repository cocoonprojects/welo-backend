<?php

namespace People\Event;

use Application\DomainEvent;
use Application\Entity\BasicUser;
use People\Entity\Organization;
use Prooph\EventSourcing\AggregateChanged;
use Rhumsaa\Uuid\Uuid;

class OrganizationMemberActivationChanged extends DomainEvent
{
    protected $memberId;

    protected $organizationId;

    protected $active;

    protected $by;

    public static function happened($aggregateId, Uuid $organizationId, Uuid $memberId, $active, BasicUser $by)
    {
        $event = self::occur($aggregateId, ['memberId' => $memberId, 'organizationId' => $organizationId, 'active' => $active, 'by' => $by]);
        $event->memberId = $memberId->toString();
        $event->organizationId = $organizationId->toString();
        $event->active = $active;
        $event->by = $by->getId();

        return $event;
    }

    public function memberId()
    {
        return is_string($this->memberId) ? $this->memberId : $this->memberId->toString();
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