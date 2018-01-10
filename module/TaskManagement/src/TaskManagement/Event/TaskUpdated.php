<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskUpdated extends DomainEvent
{
    protected $lane;

    protected $previousLane;

    protected $subject;

    protected $description;

    protected $by;

    public static function happened($aggregateId, $subject, $description, $lane = null, $previousLane = null, $by)
    {
        $event = self::occur($aggregateId, [
            'subject'=> $subject,
            'description' => $description,
            'lane' => $lane,
            'previousLane' => $previousLane,
            'by' => $by
        ]);

        $event->subject = $subject;
        $event->description = $description;
        $event->lane = $lane;
        $event->previousLane = $previousLane;
        $event->by = $by;

        return $event;
    }

    public function previousLane()
    {
        return $this->previousLane;
    }

    public function lane()
    {
        return $this->lane;
    }

    public function by()
    {
        return $this->by;
    }
}