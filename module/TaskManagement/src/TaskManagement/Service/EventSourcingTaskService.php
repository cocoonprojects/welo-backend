<?php

namespace TaskManagement\Service;

use Application\Entity\BasicUser;
use Doctrine\ORM\EntityManager;
use Application\IllegalStateException;
use People\Organization as WriteModelOrganization;
use People\Entity\Organization;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Prooph\EventStore\Aggregate\AggregateRepository;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream\SingleStreamStrategy;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\DTO\PositionData;
use TaskManagement\Entity\ItemCompletedAcceptance;
use TaskManagement\Task;
use TaskManagement\Entity\Task as ReadModelTask;
use Kanbanize\Entity\KanbanizeTask as ReadModelKanbanizeTask;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Entity\ItemIdeaApproval;
use TaskManagement\Entity\Stream;
use TaskManagement\TaskInterface;

class EventSourcingTaskService extends AggregateRepository implements TaskService
{
	private $entityManager;

	public function __construct(EventStore $eventStore, EntityManager $entityManager)
	{
		parent::__construct($eventStore, new AggregateTranslator(), new SingleStreamStrategy($eventStore), AggregateType::fromAggregateRootClass(Task::class));
		$this->entityManager = $entityManager;
	}

	public function addTask(Task $task)
	{
		$this->addAggregateRoot($task);
		return $task;
	}

	/**
	 * Retrieve task entity with specified ID
	 * @param string|Uuid $id
	 * @return Task
	 */
	public function getTask($id)
	{
		$tId = $id instanceof Uuid ? $id->toString() : $id;

		$task = $this->getAggregateRoot($tId);

		return $task;
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findTasks()
	 * @param Organization|ReadModelOrganization|String|Uuid $organization
	 * @param int $offset
	 * @param int $limit
	 * @param array $filters
	 * @return \TaskManagement\Task[]
	 */
	public function findTasks($organization, $offset, $limit, $filters, $sorting=null)
	{
		switch (get_class($organization)){
			case Organization::class :
			case WriteModelOrganization::class:
				$organizationId = $organization->getId();
				break;
			case Uuid::class:
				$organizationId = $organization->toString();
				break;
			default :
				$organizationId = $organization;
		}
		$builder = $this->entityManager->createQueryBuilder();

		$orderBy = 't.mostRecentEditAt';
		$orderType = 'DESC';

		if (isset($sorting["orderBy"])) {
			$orderBy = "t.{$sorting['orderBy']}";
		}

		if (isset($sorting["orderType"])) {
			$orderType = $sorting['orderType'];
		}

		if ($orderBy == 't.priority') {

		    $value = 999999;

		    if (strtoupper($orderType) == 'DESC') {
		        $value = -1;
            }

            $query = $builder->select("t, COALESCE(t.position, $value) AS HIDDEN position")
                             ->orderBy('position', $orderType);

        } else {

            $query = $builder->select('t')
                             ->orderBy($orderBy, $orderType);
        }

        $query = $query->from(ReadModelTask::class, 't')
                         ->innerJoin('t.stream', 's', 'WITH', 's.organization = :organization')
                         ->setFirstResult($offset)
                         ->setMaxResults($limit)
                         ->setParameter(':organization', $organizationId);


		if(isset($filters["type"]) && $filters["type"]=='decisions') {
			$query->andWhere('t.is_decision = :type')
				->setParameter('type', 1);
		}

		if(isset($filters["startOn"])){
			$query->andWhere('t.createdAt >= :startOn')
				->setParameter('startOn', $filters["startOn"]);
		}
		if(isset($filters["endOn"])){
			$query->andWhere('t.createdAt <= :endOn')
				->setParameter('endOn', $filters["endOn"]);
		}
		if(isset($filters['streamId'])) {
			$query->andWhere('t.stream = :streamId')
				->setParameter(':streamId', $filters['streamId']);
		}
		if(isset($filters["memberId"])){
			$query->innerJoin('t.members', 'm', 'WITH', 'm.user = :memberId')
				->setParameter('memberId', $filters["memberId"]);
		}
		if(isset($filters["memberEmail"])){
			$query->innerJoin('t.members', 'm')
				->innerJoin('m.user', 'u', 'WITH', 'u.email = :memberEmail')
				->setParameter('memberEmail', $filters["memberEmail"]);
		}
		if(array_key_exists('status', $filters)){
			$query->andWhere('t.status = :status')->setParameter('status', $filters["status"]);
		}

		return $query->getQuery()->getResult();
	}

	/**
	 * @see \TaskManagement\Service\TaskService::countOrganizationTasks()
	 * @param Organization $organization
	 * @param array $filters
	 * @return int
	 */
	public function countOrganizationTasks(Organization $organization, $filters){

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('count(t)')
			->from(ReadModelTask::class, 't')
			->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
			->setParameter(':organization', $organization);

		$type = 0;
		if(isset($filters["type"]) && $filters["type"]=='decisions') {
			$query->andWhere('t.is_decision = :type')
				->setParameter('type', 1);
		}

		if(isset($filters["startOn"])){
			$query->andWhere('t.createdAt >= :startOn')
			->setParameter('startOn', $filters["startOn"]);
		}
		if(isset($filters["endOn"])){
			$query->andWhere('t.createdAt <= :endOn')
			->setParameter('endOn', $filters["endOn"]);
		}
		if(isset($filters['streamId'])) {
			$query->andWhere('t.stream = :streamId')
			->setParameter(':streamId', $filters['streamId']);
		}
		if(isset($filters["memberId"])){
			$query->innerJoin('t.members', 'm', 'WITH', 'm.user = :memberId')
			->setParameter('memberId', $filters["memberId"]);
		}
		if(isset($filters["memberEmail"])){
			$query->innerJoin('t.members', 'm')
			->innerJoin('m.user', 'u', 'WITH', 'u.email = :memberEmail')
			->setParameter('memberEmail', $filters["memberEmail"]);
		}
		if(array_key_exists('status', $filters)){
			$query->andWhere('t.status = :status')->setParameter('status', $filters["status"]);
		}
		return intval($query->getQuery()->getSingleScalarResult());
	}

	public function findTask($id) {
		return $this->entityManager->find(ReadModelTask::class, $id);
	}


	public function findTaskByKanbanizeId($id) {
		return $this->entityManager
					->getRepository(ReadModelKanbanizeTask::class)
					->findOneBy(['taskId' =>  $id]);
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findCompletedTasksBefore()
	 * @param \DateInterval $interval
	 * @return ReadModelTask[]
	 */
	public function findItemsCompletedBefore(\DateInterval $interval, $orgId = null){

		$referenceDate = new \DateTime('now');

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('t')
			->from(ReadModelTask::class, 't')
			->where("DATE_ADD(t.completedAt,".$interval->format('%d').", 'DAY') <= :referenceDate")
			->andWhere('t.status = :taskStatus')
			->setParameter('taskStatus', Task::STATUS_COMPLETED)
			->setParameter('referenceDate', $referenceDate->format('Y-m-d H:i:s'));

        if(!is_null($orgId)) {
            $query->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
                ->setParameter('organization', $orgId);
        }

		return $query->getQuery()->getResult();
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findAcceptedTasksBefore()
	 * @param \DateInterval $interval
	 * @return ReadModelTask[]
	 */
	public function findAcceptedTasksBefore(\DateInterval $interval, $orgId = null){

		$referenceDate = new \DateTime('now');

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('t')
			->from(ReadModelTask::class, 't')
			->where("DATE_ADD(t.acceptedAt,".$interval->format('%d').", 'DAY') <= :referenceDate")
			->andWhere('t.status = :taskStatus')
			->setParameter('taskStatus', Task::STATUS_ACCEPTED)
			->setParameter('referenceDate', $referenceDate->format('Y-m-d H:i:s'));

        if(!is_null($orgId)) {
            $query->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
                ->setParameter('organization', $orgId);
        }

		return $query->getQuery()->getResult();
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findAcceptedTasksBetween()
	 * @param \DateInterval $after
	 * @param \DateInterval $before
	 * @return ReadModelTask[]
	 */
	public function findAcceptedTasksBetween(\DateInterval $after, \DateInterval $before, $orgId = null){

		$referenceDate = new \DateTime('now');

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('t')
			->from(ReadModelTask::class, 't')
			->where("DATE_ADD(t.acceptedAt,".$before->format('%d').", 'DAY') >= :referenceDate")
			->andWhere("DATE_ADD(t.acceptedAt,".$after->format('%d').", 'DAY') <= :referenceDate")
			->andWhere('t.status = :taskStatus')
			->setParameter('taskStatus', Task::STATUS_ACCEPTED)
			->setParameter('referenceDate', $referenceDate->format('Y-m-d H:i:s'))
        ;

		if(!is_null($orgId)) {
			$query->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
				  ->setParameter('organization', $orgId);
		}

		return $query->getQuery()->getResult();
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findIdeasCreatedBetween()
	 * @param \DateInterval $after
	 * @param \DateInterval $before
	 * @return ReadModelTask[]
	 */
	public function findIdeasCreatedBetween(\DateInterval $after, \DateInterval $before, $orgId = null){

		$referenceDate = new \DateTime('now');

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('t')
			->from(ReadModelTask::class, 't')
			->where("DATE_ADD(t.createdAt,".$before->format('%d').", 'DAY') >= :referenceDate")
			->andWhere("DATE_ADD(t.createdAt,".$after->format('%d').", 'DAY') <= :referenceDate")
			->andWhere('t.status = :taskStatus')
			->setParameter('taskStatus', Task::STATUS_IDEA)
			->setParameter('referenceDate', $referenceDate->format('Y-m-d H:i:s'));

		if(!is_null($orgId)) {
			$query->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
				  ->setParameter('organization', $orgId);
		}

		return $query->getQuery()->getResult();
	}

	/**
	 * @see \TaskManagement\Service\TaskService::findMemberStats()
	 * @param Organization $org
	 * @param string $memberId
	 * @param \DateTime $filters
	 * @return array
	 */
	public function findMemberStats(Organization $org, $memberId, $filters){
		if(is_null($memberId)){
			return [];
		}

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('COALESCE(SUM( CASE WHEN m.role=:role THEN 1 ELSE 0 END ),0) as ownershipsCount')
			->addSelect('COUNT(m.task) as membershipsCount')
			->addSelect('COALESCE(SUM(m.credits),0) as creditsCount')
			->addSelect('COALESCE(AVG( CASE WHEN t.status >=:taskStatus THEN m.delta ELSE :value END ),0) as averageDelta')
			->from(TaskMember::class, 'm')
			->innerJoin('m.task', 't')
			->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
			->innerjoin('m.user', 'u', 'WITH', 'u.id = :memberId')
			->setParameter('role', TaskMember::ROLE_OWNER)
			->setParameter('taskStatus', Task::STATUS_CLOSED)
			->setParameter('value', NULL)
			->setParameter('memberId', $memberId)
			->setParameter('organization', $org->getId());

		if(isset($filters["startOn"])){
			$query->andWhere('t.createdAt >= :startOn')
				->setParameter('startOn', $filters["startOn"]);
		}
		if(isset($filters["endOn"])){
			$query->andWhere('t.createdAt <= :endOn')
				->setParameter('endOn', $filters["endOn"]);
		}

		return $query->getQuery()->getResult()[0];
	}


    public function findMemberInvolvement(Organization $org, $memberId)
    {
		if(is_null($memberId)){
			return [];
		}

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('COALESCE(SUM( CASE WHEN m.role=:role AND t.status <:taskStatus THEN 1 ELSE 0 END ),0) as ownershipsCount')
			->addSelect('COUNT(m.task) as membershipsCount')
			->from(TaskMember::class, 'm')
			->innerJoin('m.task', 't')
			->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
			->innerjoin('m.user', 'u', 'WITH', 'u.id = :memberId')
			->setParameter('role', TaskMember::ROLE_OWNER)
			->setParameter('taskStatus', Task::STATUS_CLOSED)
			->setParameter('memberId', $memberId)
			->setParameter('organization', $org->getId());

		return $query->getQuery()->getResult()[0];
	}


	/**
	 * (non-PHPdoc)
	 * @see \TaskManagement\Service\TaskService::findItemsCreatedBefore()
	 */
	public function findItemsCreatedBefore(\DateInterval $interval, $status = null, $orgId = null){
		$referenceDate = new \DateTime('now');

		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('t')
			->from(ReadModelTask::class, 't')
			->where("DATE_ADD(t.createdAt,".$interval->format('%d').", 'DAY') <= :referenceDate")
			->setParameter('referenceDate', $referenceDate->format('Y-m-d H:i:s'));

		if($status !== null) {
			$query->andWhere('t.status = :taskStatus')
				->setParameter('taskStatus', $status);
		}

		if($orgId !== null) {
			$query->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
				  ->setParameter('organization', $orgId);
		}

		return $query->getQuery()->getResult();
	}

	public function countVotesForIdeaApproval($itemStatus, $id)
    {
        return $this->countVotesForItem($itemStatus, $id, ItemIdeaApproval::class);
    }

    public function countVotesForItemAcceptance($itemStatus, $id)
    {
        return $this->countVotesForItem($itemStatus, $id, ItemCompletedAcceptance::class);
    }

	private function countVotesForItem($itemStatus, $id, $voteType = null)
    {
	    $voteType = $voteType ?: ItemIdeaApproval::class;

		$tId = $id instanceof Uuid ? $id->toString() : $id;
		$builder = $this->entityManager->createQueryBuilder();

		$query = $builder->select ( 'COALESCE(SUM( CASE WHEN a.vote.value = 1 THEN 1 ELSE 0 END ),0) as votesFor' )
		->addSelect('COALESCE(SUM( CASE WHEN a.vote.value = 0 THEN 1 ELSE 0 END ),0) as votesAgainst')
		->from($voteType, 'a')
		->innerJoin('a.item', 'item', 'WITH', 'item.status = :status')
		->where('item.id = :id')
		->setParameter ( ':status', $itemStatus)
		->setParameter ( ':id', $tId)
		->getQuery();

		return $query->getResult()[0];
	}


	public function countItemsInLane($laneId)
    {
        $builder = $this->entityManager->createQueryBuilder();

        $query = $builder->select ( 'COUNT(item) as itemsCount' )
		->from(ReadModelTask::class, 'item')
		->where('item.lane = :lane')
		->setParameter ( ':lane', $laneId)
		->getQuery();

		return $query->getResult()[0]['itemsCount'];
	}


	public function getTaskHistory($aggregateId) {
		$streamEvents = $this->streamStrategy->read($this->aggregateType, $aggregateId);
		$events = [];

		foreach($streamEvents as $k => $v) {
			$payload = $v->payload();

			$type = explode('\\', $v->eventName()->toString());
            $type = array_pop($type);

			$events[] = [
				'id' => $v->eventId()->toString(),
				'name' => $type,
				'on' => $v->occurredOn()->format('d/m/Y H:i:s'),
				'user' => [
				    'id' => $payload['by'],
                    'name' => isset($payload['userName']) ? $payload['userName'] : ''
                ],
                'payload' => $payload
			];
		}
		return $events;
	}

    public function refreshEntity(ReadModelTask $task)
    {
        $this->entityManager->refresh($task);
    }

    public function findTasksInLaneAfter($orgId, $lane, $position)
    {
        $builder = $this->entityManager->createQueryBuilder();

        $query = $builder->select('item')
            ->from(ReadModelTask::class, 'item')
            ->innerJoin('item.stream', 's', 'WITH', 's.organization = :organization')
            ->where('item.lane = :lane')
            ->andWhere('item.position > :position')
            ->setParameter('organization', $orgId)
            ->setParameter('lane', $lane)
            ->setParameter('position', $position);

        return $query->getQuery()->getResult();
    }

    public function getNextOpenTaskPosition($taskId, $orgId, $laneId = null)
    {
        $builder = $this->entityManager->createQueryBuilder();

        $query = $builder->select ( 'COALESCE(MAX(item.position), 0) as itemPos' )
            ->from(ReadModelTask::class, 'item')
			->innerJoin('item.stream', 's', 'WITH', 's.organization = :organization')
            ->where('item.status = :status')
            ->andWhere('item.id != :id')
            ->setParameter( ':status', TaskInterface::STATUS_OPEN)
            ->setParameter( ':id', $taskId)
            ->setParameter('organization', $orgId);

        if ($laneId) {
            $query->andWhere('item.lane = :lane')
                  ->setParameter(':lane', $laneId);
        }

        return $query->getQuery()->getResult()[0]['itemPos'] + 1;
    }


    public function updateTasksPositions(Organization $organization, Stream $stream, PositionData $dto, BasicUser $by)
    {
        if ($stream->isBoundToKanbanizeBoard()) {
            throw new IllegalStateException('cannot update priorities in a board connected to kanbanize');
        }

        if (!$organization->getParams()->get('manage_priorities')) {
            throw new IllegalStateException("cannot update priorities as defined in 'manage_priorities' settings");
        }

        foreach ($dto->data as $taskId => $position) {
            $task = $this->getTask($taskId);

            $task->updatePosition($position, $by);
        }
    }
}
