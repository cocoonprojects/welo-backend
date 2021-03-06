<?php

namespace TaskManagement\Controller;

use Application\Controller\OrganizationAwareController;
use Application\Service\UserService;
use TaskManagement\Service\TaskService;
use People\Service\OrganizationService;
use TaskManagement\View\TaskJsonModel;
use People\MissingOrganizationMembershipException;
use Application\DomainEntityUnavailableException;
use TaskManagement\Task;


class OwnerController extends OrganizationAwareController
{
	protected static $resourceOptions = ['POST'];

	/**
	 * Only for organization admin
	 * @var TaskService
	 */
	protected $taskService;

	protected $userService;

	public function __construct(OrganizationService $orgService, TaskService $taskService, UserService $userService) {
		parent::__construct($orgService);		
		$this->taskService = $taskService;
		$this->userService = $userService;
	}
	
	public function invoke($id, $data)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$task = $this->taskService->getTask($id);
		if (is_null($task)) {
			$this->response->setStatusCode(404);
			return $this->response;
		}

		if (!$this->identity()->isOwnerOf($this->organization)) {
			$this->response->setStatusCode(403);
			return $this->response;
		}

		$ownerId = $data['ownerId'];
		$newOwner = $this->userService->findUser($ownerId);
		$exOwner = $this->userService->findUser($task->getOwner());
		if(is_null($newOwner)){
			$this->response->setStatusCode(404);
			return $this->response;
		}
		
		$this->transaction()->begin();
		try {
			$task->changeOwner($newOwner, $exOwner, $this->identity());
			$this->transaction()->commit();
			$this->response->setStatusCode(201);
			$view = new TaskJsonModel($this);
			$view->setVariable('resource', $task);
			return $view;
		} catch (DomainEntityUnavailableException $e) {
			$this->transaction()->rollback();
			$this->response->setStatusCode(404);
		} catch (MissingOrganizationMembershipException $e) {
			$this->transaction()->rollback();
			$this->response->setStatusCode(412);	// Preconditions failed
		}
		return $this->response;
	}
	
	protected function getResourceOptions()
	{
		return self::$resourceOptions;
	}
}