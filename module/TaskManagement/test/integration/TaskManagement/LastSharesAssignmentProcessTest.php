<?php
namespace TaskManagement;

use IntegrationTest\Bootstrap;
use PHPUnit_Framework_TestCase;
use TaskManagement\Controller\SharesController;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;
use Behat\Testwork\Tester\Setup\Teardown;

class LastSharesAssignmentTaskProcessTest extends \BaseTaskProcessTest
{	
	protected $task;
	protected $owner;
	protected $member;
	protected $organization;

	/**
	 * @var \DateInterval
	 */
	protected $intervalForCloseTasks;

	protected function setUp()
	{
        parent::setupController('TaskManagement\Controller\Shares', 'shares');

        $userService = $this->serviceManager->get('Application\UserService');
        $this->owner = $userService->findUser('60000000-0000-0000-0000-000000000000');
        $this->member = $userService->findUser('70000000-0000-0000-0000-000000000000');

        $adapter = new AdapterMock();
		$adapter->setEmail($this->owner->getEmail());
		$this->authService = $this->serviceManager->get('Zend\Authentication\AuthenticationService');
		$this->authService->authenticate($adapter);

        $streamService = $this->serviceManager->get('TaskManagement\StreamService');
        $stream = $streamService->getStream('00000000-1000-0000-0000-000000000000');

        $this->intervalForCloseTasks = new \DateInterval('P7D');
		
		$transactionManager = $this->serviceManager->get('prooph.event_store');
		$transactionManager->beginTransaction();

		try {
			$task = Task::create($stream, 'Cras placerat libero non tempor c', $this->owner);
			$task->addMember($this->owner, Task::ROLE_OWNER);
			$task->open($this->owner);
			$task->execute($this->owner);
			$task->addEstimation(1500, $this->owner);
			$task->addMember($this->member, Task::ROLE_MEMBER);
			$task->addEstimation(3100, $this->member);
			$task->complete($this->owner);
			$task->accept($this->owner, $this->intervalForCloseTasks);
			$task->assignShares([ $this->owner->getId() => 0.4, $this->member->getId() => 0.6 ], $this->member);
			$this->task = $this->taskService->addTask($task);
			$transactionManager->commit();
		} catch (\Exception $e) {
			var_dump($e->getMessage());
			$transactionManager->rollback();
			throw $e;
		}
	}

	public function testAssignSharesAsLast() {
		$this->routeMatch->setParam('id', $this->task->getId());

		$this->request->setMethod('post');
		$params = $this->request->getPost();
		$params->set($this->owner->getId(), 50);
		$params->set($this->member->getId(), 50);

		$result   = $this->controller->dispatch($this->request);
		$response = $this->controller->getResponse();

		$readModelTask = $this->controller->getTaskService()->findTask($this->task->getId());
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
		$this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
		$this->assertEquals(true, $this->task->isSharesAssignmentCompleted());
	}

	public function testSkipSharesAsLast() {
		$this->routeMatch->setParam('id', $this->task->getId());

		$this->request->setMethod('post');

		$result   = $this->controller->dispatch($this->request);
		$response = $this->controller->getResponse();

		$readModelTask = $this->controller->getTaskService()->findTask($this->task->getId());
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
		$this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
		$this->assertEquals(true, $this->task->isSharesAssignmentCompleted());
	}
}
