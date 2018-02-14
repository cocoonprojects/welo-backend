<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskMemberRemoved extends DomainEvent
{
    protected $organizationId;
    protected $userId;
    protected $userName;
    protected $by;

    public static function happened($aggregateId, $organizationId, $userId, $userName, $by)
    {
        $event = self::occur($aggregateId, ['organizationId' => $organizationId, 'userId' => $userId, 'userName' => $userName, 'by' => $by]);
        $event->organizationId = $organizationId;
        $event->userId = $userId;
        $event->userName = $userName;
        $event->by = $by;

        return $event;
    }

    public function by()
    {
        return $this->by;
    }
}