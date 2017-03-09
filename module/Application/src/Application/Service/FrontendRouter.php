<?php
namespace Application\Service;

use People\Organization;
use TaskManagement\Entity\Task;
use Application\Entity\User;

class FrontendRouter
{
    public function url($host, Task $task)
    {
        return $host . 'index.html#/' . $task->getOrganizationId() . '/items/' . $task->getId();
    }

    public function member($host, Organization $org, User $member)
    {
        return $host . 'index.html#/' . $org->getId() . '/people/' . $member->getId();
    }
}