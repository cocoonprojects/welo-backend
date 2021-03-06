<?php

namespace TaskManagement\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Application\DomainEntity;
use Application\Entity\User;
use Application\Entity\BasicUser;
use TaskManagement\Entity\Task;

/**
 * @ORM\Entity
 * @ORM\Table(name="item_approvals")
 * @ORM\MappedSuperclass
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 */
abstract class Approval {

	/**
	 * @ORM\Id
	 * @ORM\Column(type="bigint")
	 * @ORM\GeneratedValue
	 * @var bigint
	 */
	protected $id;

	/**
	 * @ORM\ManyToOne(targetEntity="Application\Entity\User")
	 * @ORM\JoinColumn(name="voter_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	protected $voter;
	
	/**
	 * @ORM\Embedded(class="TaskManagement\Entity\Vote")
	 * @var Balance
	 */
	protected $vote;
	
	/**
	 * @ORM\Column(type="datetime")
	 * @var DateTime
	 */
	protected $createdAt;
	
	/**
	 * @ORM\ManyToOne(targetEntity="Application\Entity\User")
	 * @ORM\JoinColumn(name="createdBy_id", referencedColumnName="id")
	 * @var User
	 */
	protected $createdBy;
	
	/**
	 * @ORM\Column(type="datetime")
	 * @var datetime
	 */
	protected $mostRecentEditAt;
	
	/**
	 * @ORM\ManyToOne(targetEntity="Application\Entity\User")
	 * @ORM\JoinColumn(name="mostRecentEditBy_id", referencedColumnName="id")
	 * @var BasicUser
	 */
	protected $mostRecentEditBy;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var description
	 */
	protected $description;
	
	public function getId(){
		return $this->id;
	}
	
	public function getVoter(){
		return $this->voter;
	}
	public function setVoter(User $user){
		$this->voter=$user;
		return $this;
	}
	
	public function getVote() {
		return $this->vote;
	}

	public function setVote($vote){
	  $this->vote =$vote;
	  return $this;
	}
	
	public function getCreatedAt() {
		return $this->createdAt;
	}
	
	public function setCreatedAt(\DateTime $when) {
		$this->createdAt = $when;
		return $this;
	}
		

	
	public function getCreatedBy() {
		return $this->createdBy;
	}
	
	public function setCreatedBy(User $user) {
		$this->createdBy = $user;
		return $this;
	}
	
	public function getMostRecentEditAt() {
		return $this->mostRecentEditAt;
	}
	
	public function setMostRecentEditAt(\DateTime $when) {
		$this->mostRecentEditAt = $when;
		return $this;
	}
	
	public function getMostRecentEditBy() {
		return $this->mostRecentEditBy;
	}
	
	public function setMostRecentEditBy(User $user) {
		$this->mostRecentEditBy = $user;
		return $this;
	}

    public function getDescription(){
		return $this->description;
	}
	
	public function setDescription($description){
		$this->description=$description;
		return $this;
	}
	
}
