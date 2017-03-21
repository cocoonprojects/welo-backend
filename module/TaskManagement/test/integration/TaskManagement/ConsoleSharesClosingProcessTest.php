<?php

namespace TaskManagement;

use TaskManagement\Controller\Console\SharesRemindersController;
use PHPUnit_Framework_TestCase;
use Guzzle\Http\Client;
use IntegrationTest\Bootstrap;
use Prooph\EventStore\EventStore;
use Application\Entity\User;
use People\Organization;
use People\Entity\OrganizationEntity;
use People\Service\OrganizationService;
use TaskManagement\Entity\Task as EntityTask;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Entity\Vote;
use TaskManagement\Service\TaskService;
use Zend\Console\Request as ConsoleRequest;
use TaskManagement\Service\MailService;
use Zend\Form\Element\DateTime;


class ConsoleSharesClosingProcessTest extends \PHPUnit_Framework_TestCase
{
    private $initialized = false;

	private $controller;
	private $admin;
	private $owner;
	private $member01;
	private $member02;
	private $task;
	private $transactionManager;
	private $taskService;
	private $userService;
	private $orgService;

	protected function setUp()
	{
        $serviceManager = Bootstrap::getServiceManager();

        $this->transactionManager = $serviceManager->get('prooph.event_store');

        $this->userService = $serviceManager->get('Application\UserService');
        $this->taskService = $serviceManager->get('TaskManagement\TaskService');
        $this->orgService = $serviceManager->get('People\OrganizationService');

        $this->admin = $this->createUser(['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => $this->generateRandomEmail()], User::ROLE_ADMIN);
        $this->owner = $this->createUser([ 'given_name' => 'John', 'family_name' => 'Doe', 'email' => $this->generateRandomEmail() ], User::ROLE_USER );
        $this->member01 = $this->createUser([ 'given_name' => 'Jane', 'family_name' => 'Doe', 'email' => $this->generateRandomEmail() ], User::ROLE_USER );
        $this->member02 = $this->createUser([ 'given_name' => 'Jack', 'family_name' => 'Doe', 'email' => $this->generateRandomEmail() ], User::ROLE_USER );

        $this->organization = $this->createOrganization($this->generateRandomName(), $serviceManager);
        $stream = $this->createStream($this->generateRandomName(), $this->organization, $serviceManager);


        $this->transactionManager->beginTransaction();
        try {
            $this->organization->addMember($this->owner, Organization::ROLE_MEMBER);
            $this->organization->addMember($this->member01, Organization::ROLE_MEMBER);
            $this->organization->addMember($this->member02, Organization::ROLE_MEMBER);
            $this->transactionManager->commit();

        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

        // reload users to refresh memberships inside user entities
        $this->userService->refreshEntity($this->owner);
        $this->userService->refreshEntity($this->member01);
        $this->userService->refreshEntity($this->member02);

        $this->transactionManager->beginTransaction();
        try {
            $this->task = Task::create($stream, 'Lorem Ipsum Sic Dolor Amit', $this->owner);
            $this->task->addMember($this->owner, Task::ROLE_OWNER);
            $this->task->addMember($this->member01, Task::ROLE_MEMBER);
            $this->task->addMember($this->member02, Task::ROLE_MEMBER);
            $this->task->open($this->owner);
            $this->task->execute($this->owner);
            $this->task->addEstimation(1500, $this->owner);
            $this->task->addEstimation(3100, $this->member01);
            $this->task->addEstimation(2050, $this->member02);
            $this->task->complete($this->owner);
            $this->task->accept($this->owner);

            $this->taskService->addTask($this->task);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }


		$this->controller = $serviceManager->get("ControllerManager")->get('TaskManagement\Controller\Console\SharesClosing');

		$this->request = new ConsoleRequest();
	}


    public function testCloseTaskWhereNotAllUsersAssignedShares()
	{
        $this->setSharesTimebox(0);

        $this->transactionManager->beginTransaction();
        try {
            $this->task->assignShares([
                $this->owner->getId() => 0.33,
                $this->member01->getId() => 0.57,
                $this->member02->getId() => 0.10
            ], $this->owner);
            $this->task->assignShares([
                $this->owner->getId() => 0.23,
                $this->member01->getId() => 0.47,
                $this->member02->getId() => 0.30
            ], $this->member01);
            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

        $readModelTask = $this->taskService->findTask($this->task->getId());

        ob_start();
		$this->controller->dispatch($this->request);
        $result = ob_get_clean();


        $this->assertEquals(Task::STATUS_CLOSED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_CLOSED, $readModelTask->getStatus());
        $this->assertContains('found 1 accepted items to process', $result);
        $this->assertContains('closing task '.$this->task->getId(), $result);
	}


	public function testCannotCloseTaskWhereNotMinimumSharesReached()
	{
        $this->setSharesTimebox(0);

        $this->organization = $this->orgService->getAggregateRoot($this->organization->getId());

        $this->transactionManager->beginTransaction();
        try {
            $this->task->assignShares([
                $this->owner->getId() => 0.33,
                $this->member01->getId() => 0.57,
                $this->member02->getId() => 0.10
            ], $this->owner);
            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

        $readModelTask = $this->taskService->findTask($this->task->getId());

        ob_start();
		$this->controller->dispatch($this->request);
        $result = ob_get_clean();


        $this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
        $this->assertContains('found 1 accepted items to process', $result);
        $this->assertContains('Not enough shares to close the task '.$this->task->getId(), $result);
	}


	public function testCannotCloseTaskWhenNoTimeboxReached()
	{
        $this->setSharesTimebox(10);

        $this->transactionManager->beginTransaction();
        try {
            $this->task->assignShares([
                $this->owner->getId() => 0.33,
                $this->member01->getId() => 0.57,
                $this->member02->getId() => 0.10
            ], $this->owner);
            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

        $readModelTask = $this->taskService->findTask($this->task->getId());

        ob_start();
		$this->controller->dispatch($this->request);
        $result = ob_get_clean();


        $this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
        $this->assertContains('found 0 accepted items to process in '.$this->organization->getId(), $result);
	}


    protected function generateRandomName() {
        return round(microtime(true) * 1000).'_'.rand(0,10000);
    }

    protected function generateRandomEmail() {
        return round(microtime(true) * 1000).'_'.rand(0,10000).'@foo.com';
    }

    /**
     * @return array
     */
    protected function createUser($data, $role)
    {
        return $this->userService->create($data, $role);
    }

    /**
     * @param $serviceManager
     * @param $this->admin
     * @return mixed
     */
    protected function createOrganization($name, $serviceManager)
    {
        return $this->orgService->createOrganization($name, $this->admin);
    }

    private function createStream($name, $organization, $serviceManager)
    {
        $stream = null;
        $streamService = $serviceManager->get('TaskManagement\StreamService');

        return $streamService->createStream($organization, $name, $this->admin);
    }

    /**
     * @param $this->admin
     * @throws \Exception
     */
    protected function setSharesTimebox($days)
    {
        $orgData = $this->organization->getParams();
        $orgData->params['assignment_of_shares_timebox'] = new \DateInterval("P{$days}D");

        $this->transactionManager->beginTransaction();
        try {
            $this->organization->setParams($orgData->params, $this->admin);
            $this->transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }
    }
}

