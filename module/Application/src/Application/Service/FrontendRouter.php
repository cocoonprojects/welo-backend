<?php
namespace Application\Service;

use People\Organization;
use TaskManagement\Entity\Task;
use Application\Entity\User;

class FrontendRouter
{
    protected $host;

    /**
     * @param $host the frontend host full url eg http://www.welo.com
     */
    public function __construct($host = null)
    {
        $this->host = $host;
    }

    /**
     * @deprecated use FrontendRouter::item instead
     */
    public function url($host, Task $task)
    {
        return $this->item($host, $task);
    }

    public function item($host, Task $task)
    {
        return $host . 'index.html#/' . $task->getOrganizationId() . '/items/' . $task->getId();
    }

    public function member($host, Organization $org, User $member)
    {
        return $host . 'index.html#/' . $org->getId() . '/people/' . $member->getId();
    }

}