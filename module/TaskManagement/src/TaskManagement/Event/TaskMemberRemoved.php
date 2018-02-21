<?php

namespace TaskManagement\Event;

use Application\DomainEvent;

class TaskMemberRemoved extends DomainEvent
{
    protected $organizationId;
    protected $userId;
    protected $userName;
    protected $role;
    protected $by;

    public static function happened($aggregateId, $organizationId, $userId, $userName, $role, $by)
    {
        $event = self::occur($aggregateId, ['organizationId' => $organizationId, 'userId' => $userId, 'userName' => $userName, 'role' => $role, 'by' => $by]);
        $event->organizationId = $organizationId;
        $event->userId = $userId;
        $event->userName = $userName;
        $event->role = $role;
        $event->by = $by;

        return $event;
    }

    public function by()
    {
        return $this->by;
    }

    public function userId()
    {
        return $this->userId;
    }

    public function organizationId()
    {
        return $this->organizationId;
    }
}