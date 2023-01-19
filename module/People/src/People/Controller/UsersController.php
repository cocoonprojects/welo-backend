<?php

namespace People\Controller;

use Application\Entity\User;
use Application\Service\UserService;
use TaskManagement\Service\TaskService;
use Zend\View\Model\JsonModel;
use ZFX\Rest\Controller\HATEOASRestfulController;

class UsersController extends HATEOASRestfulController
{
	protected static $resourceOptions = ['GET'];

	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var TaskService
	 */
	protected $taskService;

	public function __construct(
		UserService $userService
    )
	{
		$this->userService = $userService;
	}
    
	/**
	 * Return single resource
	 *
	 * @param  mixed $id
	 * @return mixed
	 */
	public function get($id)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$user = $this->userService->findUser($this->identity()->getId());
		if(is_null($user)){
			$this->response->setStatusCode(404);
			return $this->response;
		}

		return is_null($user) ? new JsonModel([new \stdClass()]) : new JsonModel($this->serializeOne($user));
	}

	/**
	 * @return UserService
	 */
	public function getUserService()
	{
		return $this->userService;
	}

	protected function getResourceOptions()
	{
		return self::$resourceOptions;
	}

	protected function serializeOne(User $user) {

		$userData = [
			'id'        => $user->getId(),
			'firstname' => $user->getFirstname(),
			'lastname'  => $user->getLastname(),
			'email'     => $user->getEmail(),
			'picture'   => $user->getPicture(),
		];

		return $userData;
	}

}
