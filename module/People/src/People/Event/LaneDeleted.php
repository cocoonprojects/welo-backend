<?php

namespace People\Event;

use Application\DomainEvent;
use Application\Entity\BasicUser;
use Rhumsaa\Uuid\Uuid;

class LaneDeleted extends DomainEvent
{
    protected $id;

    protected $by;

    public static function happened($aggregateId, Uuid $id, BasicUser $by)
    {
        $event = self::occur($aggregateId, ['id' => $id, 'by' => $by]);
        $event->id = $id;
        $event->by = $by;

        return $event;
    }

    public function id()
    {
        return $this->id;
    }

    public function by()
    {
        return $this->by;
    }
}