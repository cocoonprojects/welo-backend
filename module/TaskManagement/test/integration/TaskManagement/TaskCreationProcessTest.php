<?php
namespace TaskManagement;

use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;

class TaskCreationTaskProcessTest extends \BaseTaskProcessTest
{	

	protected $task;
	protected $author;
	protected $organization;
	
	/**
	 * @var \DateInterval
	 */
	protected $intervalForCloseTasks;

	protected function setUp()
	{

	    parent::setupController('TaskManagement\Controller\Tasks', '');

        $stream = $this->streamService->getStream('00000000-1000-0000-0000-000000000000');

        $this->author = $this->userService->findUser('60000000-0000-0000-0000-000000000000');

        $adapter = new AdapterMock();
        $adapter->setEmail($this->author->getEmail());
        $this->authService = $this->serviceManager->get('Zend\Authentication\AuthenticationService');
        $this->authService->authenticate($adapter);

		$this->intervalForCloseTasks = new \DateInterval('P7D');

		$transactionManager = $this->serviceManager->get('prooph.event_store');

		$transactionManager->beginTransaction();
		try {
			$task = Task::create($stream, 'Cras placerat libero non tempor', $this->author);
			$this->task = $this->taskService->addTask($task);
			$transactionManager->commit();
		} catch (\Exception $e) {
			$transactionManager->rollback();
			throw $e;
		}

	}

	public function testCheckAuthor() {
		$readModelTask = $this->taskService->findTask($this->task->getId());

        $this->assertNotNull($readModelTask->getAuthor());
        $this->assertNull($readModelTask->getOwner());
        $this->assertEquals($this->author, $readModelTask->getAuthor());
        $this->assertEmpty($readModelTask->getMembers());
	}

}
