<?php

namespace People\Entity;

use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="organization_member_contribution")
 */
class OrganizationMemberContribution
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $userId;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $taskId;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $organizationId;

    /**
     * @ORM\Column(type="float", scale=2)
     */
    private $credits;

    /**
     * @ORM\Column(type="datetime")
     */
    private $occurredOn;

    public function __construct($userId, $taskId, $organizationId, $credits, $occurredOn)
    {
        $this->userId = $userId;
        $this->taskId = $taskId;
        $this->organizationId = $organizationId;
        $this->credits = $credits;
        $this->occurredOn = $occurredOn;
    }

    public function update($credits, $occuredOn)
    {
        $this->credits = $credits;
        $this->occurredOn = $occuredOn;
    }

}