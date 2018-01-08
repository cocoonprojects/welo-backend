<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskRevertedToAccepted extends DomainEvent
{
    protected $taskSubject;

    protected $assignedCredits;

    protected $organizationId;

    protected $by;

    public static function happened($aggregateId, $taskSubject, $assignedCredits, $organizationId, $by)
    {
        $event = self::occur($aggregateId, [
            'taskSubject' => $taskSubject,
            'assignedCredits' => $assignedCredits,
            'organizationId' => $organizationId,
            'by' => $by
        ]);

        $event->taskSubject = $taskSubject;
        $event->assignedCredits = $assignedCredits;
        $event->organizationId = $organizationId;
        $event->by = $by;

        return $event;
    }

    public function taskSubject()
    {
        return $this->taskSubject;
    }

    public function assignedCredits()
    {
        return $this->assignedCredits;
    }

    public function organizationId()
    {
        return $this->organizationId;
    }

    public function by()
    {
        return $this->by;
    }
}