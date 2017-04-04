<?php

namespace Application\Controller;

use Application\Controller\OrganizationAwareController;
use Application\IllegalStateException;
use Application\View\ErrorJsonModel;
use People\Service\OrganizationService;
use TaskManagement\Service\StreamService;
use Application\Service\UserService;
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
use Kanbanize\KanbanizeTask as KanbanizeTask;

class UsersController extends OrganizationAwareController
{
	protected static $collectionOptions = [
			'GET',
			'POST'
	];
	protected static $resourceOptions = [
			'DELETE',
			'GET',
			'PUT'
	];

	/**
	 * @var UserService
	 */
	private $userService;

	public function __construct(
		UserService $taskService,
		OrganizationService $organizationService)
	{
		parent::__construct ( $organizationService );
		$this->userService = $taskService;
	}

	public function get($id) {
		if (is_null ( $this->identity () )) {
			$this->response->setStatusCode ( 401 );
			return $this->response;
		}

		$user = $this->userService->findUser($id);
		if (is_null ( $user )) {
			$this->response->setStatusCode ( 404 );
			return $this->response;
		}

		$this->response->setStatusCode ( 200 );
		$view = new UserJsonModel ( $this );
		$view->setVariable ( 'resource', $user );
		return $view;
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
	    /*
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

		$task = $this->userService->getTask($id);
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
            $task->update($data, $this->identity());

			$this->transaction ()->commit ();
			// HTTP STATUS CODE 202: Element Accepted
			$this->response->setStatusCode ( 202 );
			$view = new TaskJsonModel ( $this );
			$view->setVariable ( 'resource', $task );
			return $view;
		} catch ( \Exception $e ) {
			$this->response->setStatusCode ( 500 );
			$this->transaction ()->rollback ();
		}

		return $this->response;
		*/
	}

	public function getUserService() {
		return $this->userService;
	}

	protected function getCollectionOptions() {
		return self::$collectionOptions;
	}

	protected function getResourceOptions() {
		return self::$resourceOptions;
	}
}
