<?php

namespace People\Event;

use Application\DomainEvent;
use Application\Entity\BasicUser;
use Prooph\EventSourcing\AggregateChanged;
use Rhumsaa\Uuid\Uuid;

class OrganizationMemberRemoved extends DomainEvent
{
    protected $userId;

    protected $by;

    public static function happened($aggregateId, Uuid $userId, BasicUser $by)
    {
        $event = self::occur($aggregateId, ['userId' => $userId, 'by' => $by]);
        $event->userId = $userId;
        $event->by = $by;

        return $event;
    }

    public function userId()
    {
        return $this->userId->toString();
    }

    public function by()
    {
        return $this->by;
    }
}