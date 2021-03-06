<?php

namespace TaskManagement\Controller;

use Application\Controller\OrganizationAwareController;
use Application\IllegalStateException;
use Application\View\ErrorJsonModel;
use People\Service\OrganizationService;
use TaskManagement\Service\StreamService;
use TaskManagement\Service\TaskService;
use TaskManagement\Task;
use TaskManagement\View\TaskJsonModel;
use Zend\Filter\FilterChain;
use Zend\Filter\StringTrim;
use Zend\Filter\StripNewlines;
use Zend\Filter\StripTags;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\EmailAddress;
use Zend\Validator\GreaterThan;
use Zend\Validator\InArray as StatusValidator;
use Zend\Validator\NotEmpty;
use Zend\Validator\Regex as UuidValidator;
use Zend\Validator\ValidatorChain;
use Kanbanize\Service\KanbanizeService;
use Kanbanize\KanbanizeStream;
use Kanbanize\Entity\KanbanizeStream as ReadModelKanbanizeStream;
use Kanbanize\KanbanizeTask as KanbanizeTask;
use People\Entity\Organization;

class TasksController extends OrganizationAwareController
{
	const KANBANIZE_SETTINGS = 'kanbanize';

	protected static $collectionOptions = [
			'GET',
			'POST'
	];
	protected static $resourceOptions = [
			'DELETE',
			'GET',
			'PUT'
	];

	private $taskService;

	private $streamService;

	private $kanbanizeService;

	public function __construct(
		TaskService $taskService,
		StreamService $streamService,
		OrganizationService $organizationService,
		KanbanizeService $kanbanizeService)
	{
		parent::__construct ( $organizationService );
		$this->taskService = $taskService;
		$this->streamService = $streamService;
		$this->kanbanizeService = $kanbanizeService;
	}

	public function get($id) {
		if (is_null ( $this->identity () )) {
			$this->response->setStatusCode ( 401 );
			return $this->response;
		}

		$task = $this->taskService->findTask ( $id );
		if (is_null ( $task )) {
			$this->response->setStatusCode ( 404 );
			return $this->response;
		}

		if (! $this->isAllowed ( $this->identity (), $task, 'TaskManagement.Task.get' )) {
			$this->response->setStatusCode ( 403 );
			return $this->response;
		}

		$this->response->setStatusCode ( 200 );
		$view = new TaskJsonModel ( $this );
		$view->setVariable ( 'resource', $task );
		return $view;
	}

	/**
	 * Return a list of available tasks
	 *
	 * @method GET
	 * @link http://oraproject/task-management/tasks?streamID=[uuid]
	 * @return TaskJsonModel
	 */
	public function getList() {

		if (is_null($this->identity())) {

			$this->response->setStatusCode(401);

			return $this->response;
		}

		if (!$this->isAllowed($this->identity(), $this->organization, 'TaskManagement.Task.list')) {

		    $this->response->setStatusCode(403);

		    return $this->response;
		}

		$cardType = $this->getRequest()->getQuery("cardType");
		if (empty($cardType) || !in_array($cardType, ['all', 'decisions'])) {
			$cardType = 'all';
		}

		$filters["type"] = $cardType;

		$integerValidator = new ValidatorChain();
		$integerValidator
			->attach(new IsInt())
			->attach(new GreaterThan(['min' => 0, 'inclusive' => false]));

		$offset = $this->getRequest()->getQuery("offset");
		$offset = $integerValidator->isValid($offset) ? intval($offset) : 0;

		$limit = $this->getRequest()->getQuery("limit");
		$limit = $integerValidator->isValid($limit) ? intval($limit) : $this->organization->getParams()->get('tasks_limit_per_page');

		$filters["startOn"] = $this->getDateTimeParam("startOn");
		$filters["endOn"]   = $this->getDateTimeParam("endOn");

		$sorting = [];
		$orderBy = $this->getRequest()->getQuery("orderBy");

		if (!empty($orderBy) && in_array($orderBy, ['mostRecentEditAt', 'position'])) {
			$sorting['orderBy'] = $orderBy;
		}

		$orderType = $this->getRequest()->getQuery("orderType");
		if (!empty($orderBy) && !empty($orderType) && in_array(strtolower($orderType), ['asc','desc'])) {
			$sorting['orderType'] = $orderType;
		}

		$emailValidator = new EmailAddress(['useDomainCheck' => false]);
		$memberEmail = $this->getRequest()->getQuery("memberEmail");
		if($memberEmail && $emailValidator->isValid($memberEmail)) {
			$filters["memberEmail"] = $memberEmail;
		}

		$uuidValidator = new UuidValidator(['pattern' => '([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})']);
		$memberId = $this->getRequest()->getQuery("memberId");
		if($memberId && $uuidValidator->isValid($memberId)) {
			$filters["memberId"] = $memberId;
		}

		$streamId = $this->getRequest()->getQuery('streamId');
		if($streamId && $uuidValidator->isValid($streamId)) {
			$filters["streamId"] = $streamId;
		}

		$statusValidator = new StatusValidator([
			'haystack' => [
				Task::STATUS_IDEA,
				Task::STATUS_OPEN,
				Task::STATUS_ONGOING,
				Task::STATUS_COMPLETED,
				Task::STATUS_ACCEPTED,
				Task::STATUS_CLOSED,
				Task::STATUS_ARCHIVED
			]
		]);
		$status = $this->getRequest()->getQuery('status');
		if(!(is_null($status) || $status == '')) {
			if($statusValidator->isValid($status)) {
				$filters["status"] = $status;
			} else {
				$view = new TaskJsonModel ( $this, $this->organization );
				$view->setVariables ( [
						'resource' => [ ],
						'totalTasks' => 0
				] );
				return $view;
			}
		}

		$availableTasks = $this->taskService->findTasks($this->organization, $offset, $limit, $filters, $sorting);

		$view = new TaskJsonModel($this, $this->organization);
		$totalTasks = $this->taskService->countOrganizationTasks($this->organization, $filters);
		$view->setVariables(['resource'=>$availableTasks, 'totalTasks'=>$totalTasks]);

		return $view;
	}

	/**
	 * Create a new task into specified stream
	 *
	 * @method POST
	 * @link http://oraproject/task-management/tasks
	 * @param array $data['streamID']
	 *        	Parent stream ID of the new task
	 * @param array $data['subject']
	 *        	Task subject
	 * @return HTTPStatusCode
	 */
	public function create($data) {
		if (is_null ( $this->identity () )) {
			$this->response->setStatusCode ( 401 );
			return $this->response;
		}
		if (! $this->isAllowed ( $this->identity (), null, 'TaskManagement.Task.create' )) {
			$this->response->setStatusCode ( 403 );
			return $this->response;
		}
		$error = new ErrorJsonModel ();
		$validator = new NotEmpty ();
		if (! isset ( $data ['streamID'] )) {
			$error->addSecondaryErrors ( 'stream', [
					'Stream id cannot be empty'
			] );
		}
		if (! isset ( $data ['subject'] )) {
			$error->addSecondaryErrors ( 'subject', [
					'Subject cannot be empty'
			] );
		} else {
			$subjectFilters = new FilterChain ();
			$subjectFilters->attach ( new StringTrim () )->attach ( new StripNewlines () )->attach ( new StripTags () );
			$subject = $subjectFilters->filter ( $data ['subject'] );
			if (! $validator->isValid ( $subject )) {
				$error->addSecondaryErrors ( 'subject', [
						'Subject cannot be accepted'
				] );
			}
		}
		if (! isset ( $data ['description'] )) {
			$error->addSecondaryErrors ( 'description', [
					'Description cannot be empty'
			] );
		} else {
			$descriptionFilter = new FilterChain ();
			$descriptionFilter->attach ( new StringTrim () )->attach ( new StripTags () );
			$description = $descriptionFilter->filter ( $data ['description'] );
			if (! $validator->isValid ( $description )) {
				$error->addSecondaryErrors ( 'description', [
						'Description cannot be accepted'
				] );
			}
		}

		if (isset($data['decision'])){
			$decision = $data['decision'];

			if (!in_array($decision, ['true', 'false'])){
				$error->addSecondaryErrors('decision', ['Decision cannot be accepted']);
			}
		}

		if ($error->hasErrors ()) {
			$error->setCode ( 400 );
			$error->setDescription ( 'Specified values are not valid' );
			$this->response->setStatusCode ( 400 );
			return $error;
		}
		$aggStream = $this->streamService->getStream ( $data ['streamID'] );
		if (is_null ( $aggStream )) {
			// Stream Not Found
			$this->response->setStatusCode ( 404 );
			return $this->response;
		}

		if ($aggStream->isBoundToKanbanizeBoard()) {

			$kanbanizeSettings = $this->organization->getSettings ( $this::KANBANIZE_SETTINGS );
			if (is_null ( $kanbanizeSettings ) || empty ( $kanbanizeSettings )) {
				return $this->getResponse()
							->setContent(json_encode(new \stdClass()));
			}

			// Init KanbanizeAPI on kanbanizeService
			$this->kanbanizeService
				 ->initApi(
				 	$kanbanizeSettings['apiKey'],
				 	$kanbanizeSettings['accountSubdomain']
		 	);

			$this->transaction ()->begin ();
			try {

				$boardId = $aggStream->getBoardId();
				$opt = [];

				if (isset($data['lane'])) {
					$opt['lane'] = $data['lane'];
				}

				$mapping = $kanbanizeSettings['boards'][$boardId]['columnMapping'];
				$column = array_search(KanbanizeTask::STATUS_IDEA, $mapping);

				$opt['column'] = $column;


				$kanbanizeTaskID = $this->kanbanizeService
					->createNewTask(
						$description,
						$subject,
						$boardId,
						$opt
				);

				if (is_null ( $kanbanizeTaskID )) {
					$this->response->setStatusCode ( 417 );
					return $this->response;
				}

				$options = [
						"taskid" => $kanbanizeTaskID,
						"description" => $description,
						"columnname" => $column
				];

				if (isset($data['decision'])) {
					$options['decision'] = $data['decision'];
				}

				if (isset($data['lane'])) {
					$options['lane'] = $data['lane'];
				}

				$kanbanizeTask = KanbanizeTask::create ( $aggStream, $subject, $this->identity (), $options );
				$kanbanizeTask->setAssignee ( null, $this->identity () );
				$kanbanizeTask->setDescription ( $description, $this->identity () );

				$this->taskService->addTask ( $kanbanizeTask );
				$this->transaction ()->commit ();

				$url = $this->url ()->fromRoute ( 'tasks', array (
						'orgId' => $kanbanizeTask->getOrganizationId (),
						'id' => $kanbanizeTask->getId ()
				) );
				$this->response->getHeaders ()->addHeaderLine ( 'Location', $url );
				$this->response->setStatusCode ( 201 );
				$view = new TaskJsonModel ( $this );
				$view->setVariable ( 'resource', $kanbanizeTask );
				return $view;
			} catch ( \Exception $e ) {
				$this->transaction ()->rollback ();
				$this->response->setStatusCode ( 500 );
			}
			return $this->response;
		} else {

			$this->transaction()->begin();

			try {

				$options = [];

				if (isset($data['decision'])) {
					$options['decision'] = $data['decision'];
				}

				if (isset($data['lane'])) {
					$options['lane'] = $data['lane'];
				}

				$task = Task::create($aggStream, $subject, $this->identity(), $options);
				$task->setDescription ( $description, $this->identity () );
				$this->taskService->addTask ( $task );
				$this->transaction ()->commit ();

				$url = $this->url ()->fromRoute ( 'tasks', array (
						'orgId' => $task->getOrganizationId (),
						'id' => $task->getId ()
				) );
				$this->response->getHeaders ()->addHeaderLine ( 'Location', $url );
				$this->response->setStatusCode ( 201 );
				$view = new TaskJsonModel ( $this );
				$view->setVariable ( 'resource', $task );
				return $view;
			} catch ( \Exception $e ) {
				$this->transaction ()->rollback ();
				$this->response->setStatusCode ( 500 );
			}
			return $this->response;
		}
	}

	/**
	 * Update existing task with new data
	 *
	 * @method PUT
	 * @link http://oraproject/task-management/tasks/[:ID]
	 * @param array $id
	 *        	ID of the Task to update
	 * @param array $data['subject']
	 *        	Update Subject for the selected Task
	 * @return HTTPStatusCode
	 */
	public function update($id, $data) {
		if (is_null ( $this->identity () )) {
			$this->response->setStatusCode ( 401 );
			return $this->response;
		}
		$error = new ErrorJsonModel ();
		$validator = new NotEmpty ();
		if (! isset ( $data ['subject'] )) {
			$error->addSecondaryErrors ( 'subject', [
					'Subject cannot be empty'
			] );
		} else {
			$subjectFilters = new FilterChain ();
			$subjectFilters->attach ( new StringTrim () )->attach ( new StripNewlines () )->attach ( new StripTags () );
			$subject = $subjectFilters->filter ( $data ['subject'] );
			if (! $validator->isValid ( $subject )) {
				$error->addSecondaryErrors ( 'subject', [
						'Subject cannot be accepted'
				] );
			}
		}

		if (! isset ( $data ['description'] )) {
			$error->addSecondaryErrors ( 'description', [
					'Description cannot be empty'
			] );
		} else {
			$descriptionFilter = new FilterChain ();
			$descriptionFilter->attach ( new StringTrim () )->attach ( new StripTags () );
			$description = $descriptionFilter->filter ( $data ['description'] );
			if (! $validator->isValid ( $description )) {
				$error->addSecondaryErrors ( 'description', [
						'Description cannot be accepted'
				] );
			}
		}

		if($error->hasErrors()){
			$error->setCode(400);
			$error->setDescription('Specified values are not valid');
			$this->response->setStatusCode(400);
			return $error;
		}

		$task = $this->taskService->getTask($id);
		if(is_null($task)) {
			$this->response->setStatusCode(404);
			return $this->response;
		}

		if (! $this->isAllowed ( $this->identity (), $task, 'TaskManagement.Task.edit' )) {
			$this->response->setStatusCode ( 403 );
			return $this->response;
		}

		$this->transaction ()->begin ();
		try {

		    if (isset($data['lane']) && $data['lane']) {
		        $lanes = $this->organization->getLanes();
                $previousLane = $task->getLane();

		        $data['laneName'] = $lanes[$data['lane']];
		        $data['previousLaneName'] = !empty($previousLane) ? $lanes[$previousLane] : '';
            }
            $task->update($data, $this->identity());

			$this->transaction ()->commit ();
			// HTTP STATUS CODE 202: Element Accepted
			$this->response->setStatusCode ( 202 );
			$view = new TaskJsonModel ( $this );
			$view->setVariable ( 'resource', $task );
			return $view;
		} catch ( \Exception $e ) {
		    var_dump($e->getMessage());
			$this->response->setStatusCode ( 500 );
			$this->transaction ()->rollback ();
		}

		return $this->response;
	}

	/**
	 * Delete single existing task with specified ID
	 *
	 * @method DELETE
	 * @link http://oraproject/task-management/tasks/[:ID]
	 * @return HTTPStatusCode
	 * @author Giannotti Fabio
	 */
	public function delete($id) {

		if ($this->identity() === null) {
		    $this->response->setStatusCode(401);

		    return $this->response;
		}

		$task = $this->taskService->getTask($id);

		if ($task === null) {
		    $this->response->setStatusCode(404);

		    return $this->response;
		}

		if (!$this->isAllowed($this->identity(), $task, 'TaskManagement.Task.delete')) {
			$this->response->setStatusCode(403);

			return $this->response;
		}

		$this->transaction()->begin();

		try {

		    $task->delete($this->identity());

			$this->transaction()->commit();

			$this->response->setStatusCode(200);
		} catch ( IllegalStateException $e ) {
			$this->transaction()->rollback();
			$this->response->setStatusCode(412);
		}

		return $this->response;
	}

	public function getTaskService() {
		return $this->taskService;
	}

	protected function getCollectionOptions() {
		return self::$collectionOptions;
	}

	protected function getResourceOptions() {
		return self::$resourceOptions;
	}
}
