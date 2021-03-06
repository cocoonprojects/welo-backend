<?php
namespace TaskManagement\Entity;

use Application\Entity\BasicUser;
use Application\Entity\EditableEntity;
use Application\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use FlowManagement\FlowCardInterface;
use TaskManagement\TaskInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="tasks")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * TODO: If no DiscriminatorMap annotation is specified, doctrine uses lower-case class name as default values. Remove
 * TYPE use
 */
class Task extends EditableEntity implements TaskInterface
{
	/**
	 * @ORM\Column(type="string")
	 * @var string
	 */
	private $subject;

	/**
	 * @ORM\Column(type="string", length=800, nullable=true)
	 * @var string
	 */
	private $description;

	/**
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $status;

	/**
	 * @ORM\ManyToOne(targetEntity="Stream")
	 * @ORM\JoinColumn(name="stream_id", referencedColumnName="id", nullable=false)
	 * @var Stream
	 */
	private $stream;

	/**
	 * @ORM\OneToMany(targetEntity="TaskMember", mappedBy="task", cascade={"PERSIST", "REMOVE"}, orphanRemoval=TRUE, indexBy="member_id")
	 * @ORM\OrderBy({"createdAt" = "ASC"})
	 * @var TaskMember[]
	 */
	private $members;

	/**
	 * @ORM\OneToMany(targetEntity="ItemIdeaApproval", mappedBy="item", cascade={"PERSIST", "REMOVE"}, orphanRemoval=TRUE)
	 * @ORM\OrderBy({"createdAt" = "ASC"})
	 * @var Approval[]
	 */
	private $approvals;

	/**
	 * @ORM\OneToMany(targetEntity="ItemCompletedAcceptance", mappedBy="item", cascade={"PERSIST", "REMOVE"}, orphanRemoval=TRUE)
	 * @ORM\OrderBy({"createdAt" = "ASC"})
	 * @var Acceptance[]
	 */
	private $acceptances;

    /**
     * @ORM\OneToMany(targetEntity="FlowManagement\Entity\FlowCard", mappedBy="item", cascade={"PERSIST", "REMOVE"}, orphanRemoval=TRUE)
     * @var Task
     */
    protected $flowcards;

	/**
 	 * @ORM\Column(type="datetime", nullable=true)
	 * @var \DateTime
	 */
	protected $completedAt;

	/**
 	 * @ORM\Column(type="datetime", nullable=true)
	 * @var \DateTime
	 */
	protected $acceptedAt;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 * @var \DateTime
	 */
	protected $sharesAssignmentExpiresAt;

	/**
	 * @ORM\Column(type="boolean", nullable=false)
	 * @var boolean
	 */
	protected $is_decision = false;

	/**
	 * @ORM\Column(type="json_array", nullable=true)
	 */
	protected $attachments;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	protected $lane = '';

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 * @var integer
	 */
	protected $position;

	public function __construct($id, Stream $stream, $is_decision = false) {
		parent::__construct($id);

		$this->stream = $stream;
		$this->members = new ArrayCollection();
		$this->approvals = new ArrayCollection();
		$this->acceptances = new ArrayCollection();
		$this->flowcards = new ArrayCollection();

		$this->is_decision = $is_decision;
	}

	public function isDecision() {
		return $this->is_decision;
	}

	public function getAttachments() {
		return $this->attachments;
	}

	public function setAttachments($attachments) {
		$this->attachments = $attachments;
	}

	/**
	 * @return string
	 */
	public function getLane() {
		return $this->lane;
	}

	public function setLane($lane) {
		$this->lane = $lane;
		return $this;
	}

	public function getPosition() {
		return $this->position;
	}

	public function setPosition($position) {
		$this->position = $position;
		return $this;
	}

	public function updatePosition($position, $by, $when)
    {
        $this->position = $position;
        $this->mostRecentEditAt = $when;
        $this->mostRecentEditBy = $by;
    }

	/**
	 * @return string
	 */
	public function getSubject() {
		return $this->subject;
	}

	public function setSubject($subject) {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}

	/**
	 * @return Stream
	 */
	public function getStream() {
		return $this->stream;
	}

	/**
	 * @return string
	 */
	public function getStreamId() {
		if($this->stream) {
			return $this->stream->getId();
		}
		return null;
	}

	/**
	 * @param Stream $stream
	 * @return $this
	 */
	public function setStream(Stream $stream) {
		$this->stream = $stream;
		return $this;
	}

	public function getOrganizationId() {
		return $this->stream->getOrganization()->getId();
	}

	public function getAuthor() {
	    return $this->createdBy;
    }

    public function isAuthor(User $creator)
    {
	    return $this->createdBy !== null && $this->createdBy->getId() === $creator->getId();
    }

    /**
     * Doctrine ti odio
     */
    public function addFlowCard(FlowCardInterface $flowCard)
    {
        $this->flowcards->add($flowCard);
    }

	public function addMember(User $user, $role, BasicUser $by, \DateTime $when) {
		$taskMember = new TaskMember($this, $user, $role);
		$taskMember->setCreatedAt($when)
			->setCreatedBy($by)
			->setMostRecentEditAt($when)
			->setMostRecentEditBy($by);

		$this->members->set($user->getId(), $taskMember);

		return $this;
	}

	public function addApproval(Vote $vote, BasicUser $by, \DateTime $when ,$description) {

		$approval = new ItemIdeaApproval($vote, $when);
		$approval->setCreatedBy($by);
		$approval->setCreatedAt($when);
		$approval->setItem($this);
		$approval->setVoter($by);
		$approval->setMostRecentEditAt($when);
		$approval->setMostRecentEditBy($by);
		$approval->setDescription($description);
		
		$this->approvals->add($approval);

		return $this;
	}

	public function removeApprovals(){
		$this->approvals->clear();
		return $this;
	}

	public function addAcceptance (Vote $vote, BasicUser $by, \DateTime $when ,$description) {

		$acceptance = new ItemCompletedAcceptance($vote, $when);
		$acceptance->setCreatedBy($by);
		$acceptance->setCreatedAt($when);
		$acceptance->setItem($this);
		$acceptance->setVoter($by);
		$acceptance->setMostRecentEditAt($when);
		$acceptance->setMostRecentEditBy($by);
		$acceptance->setDescription($description);

		$this->acceptances->add($acceptance);

		return $this;
	}

	public function removeAcceptances() {
		$this->acceptances->clear();
		$this->acceptedAt = null;

		return $this;
	}

	/**
	 *
	 * @param id|User $member
	 * @return $this
	 */
	public function removeMember($member) {
		$id = $member instanceof User ? $member->getId() : $member;
		$this->members->remove($id);
		return $this;
	}

	/**
	 *
	 * @param id|BasicUser $user
	 * @return TaskMember|NULL
	 */
	public function getMember($user) {
		$key = $user instanceof BasicUser ? $user->getId() : $user;
			return $this->members->get($key);
	}

	/**
	 * @return null|TaskMember
	 */
	public function getOwner() {
		foreach ($this->members as $key => $member){
			if($member->getRole() == self::ROLE_OWNER)
				return $member;
		}
		return null;
	}

	/**
	 *
	 * @param id|BasicUser $user
	 * @return boolean
	 */
	public function hasMember($user) {
		$key = $user instanceof BasicUser ? $user->getId() : $user;
		return $this->members->containsKey($key);
	}

	/**
	 * @return TaskMember[]
	 */
	public function getMembers() {
		return $this->members->toArray();
	}

	/**
	 * @return int
	 */
	public function countMembers() {
		return count($this->members->toArray());
	}

	/**
	 * @return Approval[]
	 */
	public function getApprovals(){
		return $this->approvals->toArray();
	}

	/**
	 * @return Approval[]
	 */
	public function getAcceptances(){
		return $this->acceptances->toArray();
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getType(){
		return 'task';
	}

	/**
	 * TODO: da rimuovere, deve leggere un valore già calcolato. Il calcolo sta nel write model
	 * @return string|number|NULL
	 */
	public function getAverageEstimation() {
		$tot = null;
		$estimationsCount = 0;
		$notEstimationCount = 0;
		foreach ($this->members as $member) {
			$estimation = $member->getEstimation();

			if ($estimation) {
                $estimationValue = $estimation->getValue();
                switch ($estimationValue) {
                    case null:
                        break;
                    case Estimation::NOT_ESTIMATED:
                        $notEstimationCount++;
                        break;
                    default:
                        $tot += $estimationValue;
                        $estimationsCount++;
                }
            }
		}
		if($notEstimationCount == count($this->members)) {
			return Estimation::NOT_ESTIMATED;
		}
		if(($estimationsCount + $notEstimationCount) == count($this->members) || $estimationsCount > 2) {
			return round($tot / $estimationsCount, 2);
		}
		return null;
	}

	public function resetShares() {
		foreach ($this->members as $member) {
			$member->resetShares();
			$member->setShare(null, new \DateTime());
		}
	}

	public function resetCredits() {
        foreach ($this->members as $member) {
            $member->resetCredits(new \DateTime());
        }
    }

	public function updateMembersShare(\DateTime $when) {
		$shares = $this->getMembersShare();
		foreach ($shares as $key => $value) {
			$this->members->get($key)->setShare($value, $when);
		}
	}

	public function getSharesSummary()
    {
        $summary = [];

        $gap = $this->getGap();
        $shares = $this->getMembersShare();

        $avgCredits = $this->getAverageEstimation();

        foreach ($shares as $uid => $share) {

            $member = $this->getMember($uid)->getUser();

            $summary[$uid] = [
                'name' => $member->getFirstname(). ' ' . $member->getLastname(),
                'share' => number_format($share * 100, 1),
                'value' => $share * $avgCredits,
                'gap' => isset($gap[$uid]) ? number_format($gap[$uid] * 100, 1) : 'n/a'
            ];
        }

        return $summary;
    }

	public function getGap() {

	    $rv = [];
        $selfShare = [];

        foreach ($this->members as $taskMember) {

            foreach ($taskMember->getShares() as $index => $taskShares) {
                $memberId = $taskMember->getUser()->getId();
                $valuedId = $index;

                if ($memberId === $valuedId) {

                    $selfShare[$valuedId] = $taskShares->getValue();
                }

                $rv[$valuedId][$memberId] = $taskShares->getValue();
            }
        }
        $avgs = [];
        $gaps = [];

        foreach($rv as $uid => $singleShare) {
            $votes = array_filter($singleShare, function($x) { return !empty($x); });

            $avgs[$uid] = array_sum($votes) / count($votes);
        }


        foreach($avgs as $uid => $avg) {
            if (!isset($selfShare[$uid])) {
                continue;
            }
            $gaps[$uid] = ($selfShare[$uid] - $avg);
        }

        return $gaps;
    }

	public function getMembersShare() {
		$rv = [];

		foreach ($this->members as $member) {
			$rv[$member->getMember()->getId()] = null;
		}

		$evaluators = 0;

		foreach ($this->members as $evaluatorId => $info) {
			if(count($info->getShares()) > 0 && $info->getShareValueOf($info) !== null) {

			    $evaluators++;

				foreach($info->getShares() as $valuedId => $share) {
					$rv[$valuedId] = isset($rv[$valuedId]) ? $rv[$valuedId] + $share->getValue() : $share->getValue();
				}
			}
		}

		if($evaluators > 0) {
			array_walk($rv, function(&$value) use ($evaluators) {
				$value = round($value / $evaluators, 4);
			});
		}

		return $rv;
	}

    public function countMembersShare() {
        $evaluators = 0;
        foreach ($this->members as $evaluatorId => $info) {
            if(count($info->getShares()) > 0 && $info->getShareValueOf($info) !== null) {
                $evaluators++;
            }
        }
        return $evaluators;
    }

	public function getResourceId(){
		return 'Ora\Task';
	}

	public function getMemberRole($user)
	{
		$memberFound = $this->getMember($user);
		if($memberFound instanceof TaskMember) {
			return $memberFound->getRole();
		}
		return null;
	}

    public function setCompletedAt(\DateTime $date = null) {
        $this->completedAt = $date;
    }

	/**
	 * @return \DateTime
	 */
	public function getAcceptedAt() {
		return $this->acceptedAt;
	}

	public function setAcceptedAt(\DateTime $date) {
		$this->acceptedAt = $date;
	}

	public function getSharesAssignmentExpiresAt() {
		return $this->sharesAssignmentExpiresAt;
	}

	public function setSharesAssignmentExpiresAt(\DateTime $date) {
		$this->sharesAssignmentExpiresAt = $date;
	}

	public function resetAcceptedAt(){
		$this->acceptedAt = null;
	}


	public function removeAllParticipants()
    {
        $this->members->clear();
    }

	/**
	 * Retrieve members that haven't assigned any share
	 *
	 * @return TaskMember[]
	 */
	public function findMembersWithEmptyShares()
	{
		return array_filter($this->getMembers(), function($member) {
			return empty($member->getShares());
		});
	}

	/**
	 * @return TaskMember[]
	 */
	public function findMembersWithNoApproval()
	{
		$membersWhoVoted = [];
		foreach ($this->getApprovals() as $approval) {
			$membersWhoVoted[] = $approval->getVoter()->getId();
		}

		return array_filter($this->getMembers(), function($member) use ($membersWhoVoted) {
			return !in_array($member->getUser()->getId(), $membersWhoVoted);
		});
	}

	/**
	 * @return TaskMember[]
	 */
	public function findMembersWithNoEstimation()
	{
		return array_filter($this->getMembers(), function($member) {
			return $member->getEstimation() == null || $member->getEstimation()->getValue() == null;
		});
	}

	/**
	 * @param id|BasicUser $user
	 * @return boolean|null
	 */
	public function areSharesAssignedFromMember($user) {
		$taskMember = $this->getMember($user);
		if($taskMember != null){
			return !empty($taskMember->getShares());
		}
		return false;
	}

	/**
	 * @param id|BasicUser $user
	 * @return boolean
	 */
	public function isIdeaVotedFromMember($user){
		$approvals = $this->getApprovals();
		if($approvals!=null){
			foreach ($approvals as $approval){
				if($approval->getVoter()->getId() == $user->getId())
					return true;
			}
		}
		return false;
	}
	/**
	 * @param id|BasicUser $user
	 * @return boolean
	 */
	public function isCompletedVotedFromMember($user){
		$acceptances = $this->getAcceptances();
		if($acceptances!=null){
			foreach ($acceptances as $acceptance){
				if($acceptance->getVoter()->getId() == $user->getId())
					return true;
			}
		}
		return false;
	}

	public function isSharesAssignmentCompleted() {
		foreach ($this->members as $taskMember) {
			if(empty($taskMember->getShares())) {
				return false;
			}
		}
		return true;
	}

	public function isSharesAssignmentExpired(\DateTime $ref){
		if(is_null($this->sharesAssignmentExpiresAt)){
			return false;
		}
		return $ref > $this->sharesAssignmentExpiresAt;
	}

	public function revertToOpen(User $user, $position, \DateTime $time)
    {
        $this->setStatus( Task::STATUS_OPEN );
        $this->setPosition($position);
        $this->setMostRecentEditBy( $user );
        $this->setMostRecentEditAt( $time );
        $this->removeAllParticipants();
    }

	public function revertToIdea(User $user, \DateTime $time)
    {
        $this->setStatus( Task::STATUS_IDEA );
        $this->setMostRecentEditBy( $user );
        $this->setMostRecentEditAt( $time );
        $this->removeApprovals();
    }

    public function revertToOngoing(User $user, \DateTime $time)
    {
        $this->setStatus(Task::STATUS_ONGOING);
        $this->setMostRecentEditBy($user);
        $this->setMostRecentEditAt($time);
        $this->setCompletedAt(null);
        $this->removeAcceptances();
    }
}