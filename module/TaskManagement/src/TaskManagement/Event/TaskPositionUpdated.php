<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskPositionUpdated extends DomainEvent
{
    protected $position;

    protected $oldPosition;

    protected $by;

    public static function happened($aggregateId, $position, $oldPosition, $by)
    {
        $event = self::occur($aggregateId, ['position'=> $position, 'oldPosition' => $oldPosition, 'by' => $by]);
        $event->position = $position;
        $event->oldPosition = $oldPosition;
        $event->by = $by;

        return $event;
    }

    public function position()
    {
        return $this->position;
    }

    public function by()
    {
        return $this->by;
    }
}