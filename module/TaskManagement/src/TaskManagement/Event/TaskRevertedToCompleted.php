<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskRevertedToCompleted extends DomainEvent
{
    protected $previousState;

    protected $by;

    public static function happened($aggregateId, $previousState, $by)
    {
        $event = self::occur($aggregateId, ['previousState' => $previousState, 'by' => $by]);
        $event->previousState = $previousState;
        $event->by = $by;

        return $event;
    }

    public function previousState()
    {
        return $this->position;
    }

    public function by()
    {
        return $this->by;
    }
}