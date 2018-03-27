<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskUpdated extends DomainEvent
{
    protected $lane;

    protected $previousLane;

    protected $subject;

    protected $previousSubject;

    protected $description;

    protected $previousDescription;

    protected $by;

    public static function happened($aggregateId, $subject, $description, $lane = null, $previousSubject = null, $previousDescription = null, $previousLane = null, $by)
    {
        $event = self::occur($aggregateId, [
            'subject'=> $subject,
            'description' => $description,
            'lane' => $lane,
            'previousSubject' => $previousSubject,
            'previousDescription' => $previousDescription,
            'previousLane' => $previousLane,
            'by' => $by->getId(),
            'userName' => $by->getFirstname().' '.$by->getLastname(),
        ]);

        $event->subject = $subject;
        $event->description = $description;
        $event->lane = $lane;
        $event->previousSubject = $previousSubject;
        $event->previousDescription = $previousDescription;
        $event->previousLane = $previousLane;
        $event->by = $by->getId();

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