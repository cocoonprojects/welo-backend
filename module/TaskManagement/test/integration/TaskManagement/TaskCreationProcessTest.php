<?php
namespace TaskManagement;

use Application\Entity\User;
use Test\TestFixturesHelper;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;

class TaskCreationProcessTest extends \BaseTaskProcessTest
{	

	protected $task;
	protected $admin;
	protected $organization;
	
	/**
	 * @var \DateInterval
	 */
	protected $intervalForCloseTasks;

	protected function setUp()
	{

        $this->transactionManager = $this->serviceManager->get('prooph.event_store');

        $this->admin = $this->createUser(['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => TestFixturesHelper::generateRandomEmail()], User::ROLE_ADMIN);

        $this->organization = $this->createOrganization(TestFixturesHelper::generateRandomName(), $this->admin);
        $stream = $this->createStream(TestFixturesHelper::generateRandomName(), $this->organization, $this->admin, $this->serviceManager);

        $this->transactionManager->beginTransaction();


        try {
            $task = Task::create($stream, 'Cras placerat libero non tempor', $this->admin);
            $this->task = $this->taskService->addTask($task);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }
	}

	public function testCheckAuthor() {
		$readModelTask = $this->taskService->findTask($this->task->getId());

        $this->assertNotNull($readModelTask->getAuthor());
        $this->assertNull($readModelTask->getOwner());
        $this->assertEquals($this->admin, $readModelTask->getAuthor());
        $this->assertEmpty($readModelTask->getMembers());
	}

}
