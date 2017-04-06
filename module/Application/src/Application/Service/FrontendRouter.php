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

    public function item(Task $task)
    {
        return $this->host . 'index.html#/' . $task->getOrganizationId() . '/items/' . $task->getId();
    }

    public function member($org, $member, $absolute = true)
    {
        $orgId = $org instanceof Organization ? $org->getId() : $org;
        $memberId = $member instanceof User ? $member->getId() : $member;

        $url = "#/$orgId/people/$memberId";

        if (!$absolute) {
            return $url;
        }

        return "{$this->host}index.html$url";
    }

}