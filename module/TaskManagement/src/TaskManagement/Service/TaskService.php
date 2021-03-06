<?php

namespace TaskManagement\Service;

use Application\Entity\BasicUser;
use People\Entity\Organization;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\Entity\Stream;
use TaskManagement\Task;
use TaskManagement\DTO\PositionData;

/**
 * TODO: Rename in TaskRepository?
 */
interface TaskService
{
	/**
	 * 
	 * @param Task $task
	 * @return Task
	 */
	public function addTask(Task $task);
	/**
	 * 
	 * @param string|Uuid $id
	 * @return Task|null
	 */
	public function getTask($id);

	/**
	 * Get the list of all available tasks in the $offset - $limit interval
	 *
	 * @param Organization|ReadModelOrganization|String|Uuid $organization
	 * @param integer $offset
	 * @param integer $limit
	 * @param array $filters
	 * @return Task[]
	 */
	public function findTasks($organization, $offset, $limit, $filters, $sorting=null);

	/**
	 * @param string|Uuid $id
	 * @return Task|null
	 */
	public function findTask($id);

	/**
	 * Find completed tasks with complete date before $interval days from now
	 * @param \DateInterval $interval
	 * @return array
	 */
	public function findItemsCompletedBefore(\DateInterval $interval, $orgId = null);

	/**
	 * Find accepted tasks with accepted date before $interval days from now
	 * @param \DateInterval $interval
	 * @return array
	 */
	public function findAcceptedTasksBefore(\DateInterval $interval, $orgId = null);

	/**
	 * Find accepted tasks with accepted date before $interval days from now
	 * @param \DateInterval $interval
	 * @return array
	 */
	public function findAcceptedTasksBetween(\DateInterval $after, \DateInterval $before);

	/**
	 * Find accepted tasks with accepted date before $before days from now
	 * and after $after days since now (in the past)
	 * @param \DateInterval $after
	 * @param \DateInterval $before
	 * @return array
	 */
	public function findIdeasCreatedBetween(\DateInterval $after, \DateInterval $before);

	/**
	 * Get the number of tasks of an $organization
	 * @param Organization $organization
	 * @param \DateTime $filters["startOn"]
	 * @param \DateTime $filters["endOn"]
	 * @param String $filters["memberId"]
	 * @param String $filters["memberEmail"]
	 * @return integer
	 */
	public function countOrganizationTasks(Organization $organization, $filters);

	/**
	 * Get tasks statistics for $memberId 
	 * @param Organization $org
	 * @param string $memberId
	 * @param \DateTime $filters["startOn"]
	 * @param \DateTime $filters["endOn"]
	 */
	public function findMemberStats(Organization $org, $memberId, $filters);

    /**
     * Get tasks actual involvement for $memberId
     * @param Organization $org
     * @param string $memberId
     */
    public function findMemberInvolvement(Organization $org, $memberId);

	/**
	 * Find items with creation date before $interval days from now
	 * @param \DateInterval $interval
	 * @param string $status
	 */
	public function findItemsCreatedBefore(\DateInterval $interval, $status);
	
    public function countVotesForIdeaApproval($itemStatus, $id);

    public function countVotesForItemAcceptance($itemStatus, $id);

    public function updateTasksPositions(Organization $organization, Stream $stream, PositionData $data, BasicUser $by);

}
