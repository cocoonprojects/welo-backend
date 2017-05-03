<?php

namespace Kanbanize\Service;

use Application\Entity\User;
use Doctrine\ORM\EntityManager;
use Kanbanize\KanbanizeTask;
use TaskManagement\Entity\Stream as ReadModelStream;
use Kanbanize\Entity\KanbanizeStream;
use Kanbanize\Entity\KanbanizeTask as ReadModelKanbanizeTask;
use People\Organization as WriteModelOrganization;
use People\Entity\Organization;

/**
 * Service Kanbanize
 *
 * @author Andrea Lupia <alupia@dimes.unical.it>
 *
 */
class KanbanizeServiceImpl implements KanbanizeService
{
	/**
	 *
	 * @var EntityManager
	 */
	private $entityManager;

	/**
	 *
	 * @var KanbanizeAPI
	 */
	private $kanbanize;

	private $kanbanizeLanes;

	/*
	 * Constructs service
	 */
	public function __construct(EntityManager $em, KanbanizeAPI $api) {
		$this->kanbanize = $api;
		$this->entityManager = $em;
	}

	public function getBoardStructure($boardId)
	{
		$response = $this->kanbanize
						 ->getBoardStructure($boardId);

		return $response;
	}

	public function getFullBoardStructure($boardId)
	{
		$response = $this->kanbanize
						 ->getFullBoardStructure($boardId);

		return $response;
	}

	public function getBoardActivities($boardId)
	{
		$now = new \DateTimeImmutable('now');
		$fromDate = $now->sub(new \DateInterval('P1D'))->format('Y-m-d');
		$toDate = $now->add(new \DateInterval('P1D'))->format('Y-m-d');

		$response = $this->kanbanize
						 ->getBoardActivities($boardId, $fromDate, $toDate);

		return $response;
	}

	public function blockTask($boardid, $taskid, $reason)
	{
		$response = $this->kanbanize
			 			 ->blockTask($boardid, $taskid, 'block', $reason);

		return $response;
	}

	public function getTaskDetails($boardId, $taskId)
	{
		$response = $this->kanbanize->getTaskDetails($boardId, $taskId);

		return $response;
	}

	public function updateTask(ReadModelKanbanizeTask $task, $kanbanizeTask, $boardid)
	{
		$changeData = [];

		if (isset($kanbanizeTask['assignee']) && $task->getAssigneeName() != $kanbanizeTask['assignee']) {
			$changeData['assignee'] = $task->getAssigneeName();
		}

		if (isset($kanbanizeTask['title']) && $task->getSubject() != $kanbanizeTask['title']) {
			$changeData['title'] = $task->getSubject();
		}

		if (isset($kanbanizeTask['description']) && $task->getDescription() != $kanbanizeTask['description']) {
			$changeData['description'] = $task->getDescription();
		}

		if (empty($changeData)) {
			return;
		}

		$response = $this->kanbanize
						 ->editTask($boardid, $task->getTaskId(), $changeData);

		return $response;
	}

	public function moveTask(KanbanizeTask $task, $status) {
		$boardId = $task->getKanbanizeBoardId();
		$taskId = $task->getKanbanizeTaskId();
		$options = [];

		if ($task->getLane()) {
			$options['lane'] = $this->getLaneName($task->getLane(), $boardId);
		}

		$response = $this->kanbanize
						 ->moveTask($boardId, $taskId, $status, $options);

		if($response != 1) {
			throw new OperationFailedException('Unable to move the task ' + $taskId + ' in board ' + $boardId + 'to the column ' + $status + ' because of ' + $response);
		}

		return 1;
	}

	public function moveTaskonKanbanize(ReadModelKanbanizeTask $kanbanizeTask, $status, $boardId)
    {
        $taskId = $kanbanizeTask->getTaskId();
        $options = [];

        if ($kanbanizeTask->getLane()) {
            $options['lane'] = $this->getLaneName($kanbanizeTask->getLane(), $boardId);
        }

        if (!$this->laneExists($kanbanizeTask->getLane(), $boardId)) {
            unset($options['lane']);
//            throw new OperationFailedException('Unable to move the task ' . $taskId . ' in board ' . $boardId . ' to the column ' . $status . ' because that lane does not exists on Kanbanize', 400);
        }

        $response = $this->kanbanize
            ->moveTask($boardId, $taskId, $status, $options);

        if ($response != 1) {
            throw new OperationFailedException('Unable to move the task ' . $taskId . ' in board ' . $boardId . ' to the column ' . $status . ' because of ' . var_export($response,1), 400);
        }
		return 1;
	}


	public function createNewTask($taskSubject, $taskTitle, $boardId, $options, array $mapping = null) {
		$createdAt = new \DateTime ();

		// TODO: Modificare createdBy per inserire User
		$createdBy = "NOME UTENTE INVENTATO";

		$all_options = array_merge($options, [
			'title' => $taskTitle,
			'description' => $taskSubject,
		]);

        if ($options['lane']) {
            $options['lane'] = $this->getLaneName($options['lane'], $boardId);
        }


        $id = $this->kanbanize
				   ->createNewTask($boardId, $all_options);

		if (is_null ( $id )) {
			throw OperationFailedException("Cannot create task on Kanbanize");
		}
		return $id;
	}

	public function deleteTask(KanbanizeTask $task) {
		$ans = $this->kanbanize->deleteTask($task->getKanbanizeBoardId(), $task->getKanbanizeTaskId());
		if (isset($ans["Error"]))
			throw new OperationFailedException($ans["Error"]);
		return $ans;
	}

	public function getTasks($boardId, $status = null) {
		$tasks_to_return = array ();
		$tasks = $this->kanbanize->getAllTasks ( $boardId );
		if (is_null ( $status ))
			return $tasks;
		else {
			foreach ( $tasks as $singletask ) {
				if ($singletask ["columnname"] == $status)
					$tasks_to_return [] = $singletask;
			}

			return $tasks_to_return;
		}
	}
	public function acceptTask(KanbanizeTask $task) {
		$info = $this->kanbanize->getTaskDetails($task->getKanbanizeBoardId(), $task->getKanbanizeTaskId());
		if(isset($info['Error'])) {
			throw new OperationFailedException($info["Error"]);
		}
		if ( $info['columnname'] == KanbanizeTask::COLUMN_ACCEPTED)
		{
			return;
		}
		if($info['columnname'] == KanbanizeTask::COLUMN_COMPLETED)
		{
			$this->moveTask($task, KanbanizeTask::COLUMN_ACCEPTED);
		}else{
			throw new IllegalRemoteStateException("Cannot accpet a task which is " + $info["columnname"]);
		}
	}
	public function executeTask(KanbanizeTask $task) {
		$info = $this->kanbanize->getTaskDetails($task->getKanbanizeBoardId(), $task->getKanbanizeTaskId());
		if(isset($info['Error'])) {
			throw new OperationFailedException($info["Error"]);
		}
		if($info["columnname"] == KanbanizeTask::COLUMN_ONGOING)
		{
			return;
		}

		if($info['columnname'] == KanbanizeTask::COLUMN_COMPLETED || $info['columnname'] == KanbanizeTask::COLUMN_OPEN)
		{
			$this->moveTask($task, KanbanizeTask::COLUMN_ONGOING);
		}else{
			throw new IllegalRemoteStateException("Cannot move task in ongoing from "+$info["columnname"]);
		}
	}

	public function completeTask(KanbanizeTask $task) {
		$info = $this->kanbanize->getTaskDetails($task->getKanbanizeBoardId(), $task->getKanbanizeTaskId());
		if(isset($info['Error'])) {
			throw new OperationFailedException($info["Error"]);
		}
		if($info["columnname"] == KanbanizeTask::COLUMN_COMPLETED)
		{
			return;
		}
		if (in_array($info['columnname'], [KanbanizeTask::COLUMN_ONGOING, KanbanizeTask::COLUMN_ACCEPTED])) {
			$this->moveTask($task, KanbanizeTask::COLUMN_COMPLETED);
		} else {
			throw new IllegalRemoteStateException("Cannot move task in completed from "+$info["columnname"]);
		}
	}

	public function closeTask(KanbanizeTask $task) {
		// TODO: To be implemented
	}
	/**
	 * (non-PHPdoc)
	 * @see \Kanbanize\Service\KanbanizeService::findStreamByBoardId()
	 */
	public function findStreamByBoardId($boardId, $organization)
    {
		switch (get_class($organization))
        {
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
		$query = $builder->select ( 's' )
			->from(KanbanizeStream::class, 's')
			->where('s.organization = :organization')
			->andWhere('s.boardId = :boardId')
			->setParameter ( ':organization', $organizationId )
			->setParameter ( ':boardId', $boardId );
		return $query->getQuery()->getOneOrNullResult();
	}
	/**
	 * (non-PHPdoc)
	 * @see \Kanbanize\Service\KanbanizeService::findStreamByBoardId()
	 */
	public function findStreamByProjectId($projectId, $organization)
    {
		$test = 'test';
		try {
		switch (get_class($organization))
        {
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
		$query = $builder->select ( 's' )
			->from(KanbanizeStream::class, 's')
			->where('s.organization = :organization')
			->andWhere('s.projectId = :projectId')
			->setParameter(':organization', $organizationId)
			->setParameter(':projectId', $projectId);
		$test = $query->getQuery()->getOneOrNullResult();
	} catch (\Exception $e) {
		var_dump('Eccezione: '.$e->getTraceAsString());
	}
		return $test;
	}

	public function findStreamByOrganization($organization)
    {

	    if (is_string($organization)) {
            $organizationId = $organization;
        } else {
            switch (get_class($organization)) {
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
        }

		$builder = $this->entityManager->createQueryBuilder();

		$query = $builder->select ( 's' )
			->from(ReadModelStream::class, 's')
			->where('s.organization = :organization')
			->orderBy('s.createdAt', 'DESC')
			->setMaxResults(1)
			->setParameter(':organization', $organizationId);

		return $query->getQuery()->getOneOrNullResult();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Kanbanize\Service\KanbanizeService::findTask()
	 */
	public function findTask($taskId, $organization)
    {
		switch (get_class($organization))
        {
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
		$query = $builder->select('t')
			->from(ReadModelKanbanizeTask::class, 't')
			->innerjoin('t.stream', 's', 'WITH', 's.organization = :organization')
			->where('t.taskId = :taskId')
			->setParameter(':organization', $organizationId)
			->setParameter(':taskId', $taskId);
		return $query->getQuery()->getOneOrNullResult();
	}

	public function initApi($apiKey, $subdomain)
    {
		if(is_null($apiKey)) {
			throw new KanbanizeApiException("Cannot connect to Kanbanize due to missing api key");
		}
		if(is_null($subdomain)) {
			throw new KanbanizeApiException("Cannot connect to Kanbanize due to missing account subdomain");
		}
		$this->kanbanize->setApiKey($apiKey);
		$this->kanbanize->setUrl(sprintf(Importer::API_URL_FORMAT, $subdomain));

        $this->kanbanizeLanes = $this->kanbanize->getFullBoardStructure($boardId)['lanes'];

		return $this;
	}

    /**
     * @param ReadModelKanbanizeTask $kanbanizeTask
     * @param $boardId
     * @param $options
     * @return mixed
     */
    public function getLaneName($laneId, $boardId)
    {
        $lanePos = array_search($laneId, array_column($this->kanbanizeLanes, 'lcid'));
        return $lanePos!==false ? $this->kanbanizeLanes[$lanePos]['lcname'] : '';
    }

    /**
     * @param ReadModelKanbanizeTask $kanbanizeTask
     * @param $boardId
     * @param $options
     * @return mixed
     */
    public function laneExists($laneId, $boardId)
    {
        return array_search($laneId, array_column($this->kanbanizeLanes, 'lcid'))!==false;
    }
}