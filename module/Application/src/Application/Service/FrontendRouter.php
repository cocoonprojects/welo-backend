<?php
namespace Application\Service;

use TaskManagement\Entity\Task;

class FrontendRouter
{
    public function url($host, Task $task)
    {
        return $host . 'index.html#/' . $task->getOrganizationId() . '/items/' . $task->getId();
    }
}