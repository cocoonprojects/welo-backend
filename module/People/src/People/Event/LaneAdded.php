<?php

namespace People\Event;

use Application\DomainEvent;
use Application\Entity\BasicUser;
use Rhumsaa\Uuid\Uuid;

class LaneAdded extends DomainEvent
{
    protected $id;

    protected $name;

    protected $by;

    public static function happened($aggregateId, Uuid $id, $name, BasicUser $by)
    {
        $event = self::occur($aggregateId, ['id' => $id, 'name'=> $name, 'by' => $by]);
        $event->id = $id;
        $event->name = $name;
        $event->by = $by;

        return $event;
    }

    public function id()
    {
        return $this->id;
    }

    public function name()
    {
        return $this->name;
    }

    public function by()
    {
        return $this->by;
    }
}