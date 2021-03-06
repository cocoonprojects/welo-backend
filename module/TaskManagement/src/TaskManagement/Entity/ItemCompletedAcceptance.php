<?php

namespace TaskManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 */
class ItemCompletedAcceptance extends Approval
{
    /**
     * @ORM\ManyToOne(targetEntity="TaskManagement\Entity\Task" , inversedBy="acceptances")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $item;

	public function __construct(Vote $vote, \DateTime $createdAt)
    {
		$this->vote = $vote;
		$this->createdAt = $createdAt;
	}

    public function getItem()
    {
        return $this->item;
    }

    public function setItem(Task $item)
    {
        $this->item = $item;

        return $this;
    }

    public function getVote()
    {
        return $this->vote;
    }
}