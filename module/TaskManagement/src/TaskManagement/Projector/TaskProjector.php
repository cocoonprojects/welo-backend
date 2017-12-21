<?php

namespace TaskManagement\Projector;

use Application\Service\Projector;
use TaskManagement\Event\TaskPositionUpdated;

class TaskProjector extends Projector
{
    public function getRegisteredEvents()
    {
        return [
            TaskPositionUpdated::class
        ];
    }

    public function applyTaskPositionUpdated(TaskPositionUpdated $event)
    {
        dump($event);
    }

}