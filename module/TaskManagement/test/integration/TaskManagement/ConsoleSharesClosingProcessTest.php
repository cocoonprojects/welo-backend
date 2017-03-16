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

class ConsoleSharesClosingProcessTest extends \PHPUnit_Framework_TestCase
{

	private $controller;
	private $owner;
	private $member;
	private $task;
	private $transactionManager;
	private $taskService;
	private $userService;

	protected function setUp()
	{
        $serviceManager = Bootstrap::getServiceManager();

        $this->userService = $serviceManager->get('Application\UserService');
        $this->taskService = $serviceManager->get('TaskManagement\TaskService');
        $streamService = $serviceManager->get('TaskManagement\StreamService');
        $transactionManager = $serviceManager->get('prooph.event_store');

        $admin = $this->userService->create( [ 'given_name' => 'Admin', 'family_name' => 'Uber', 'email' => 'admin@foo.com' ], User::ROLE_ADMIN );
        $this->owner = $this->userService->create( [ 'given_name' => 'John', 'family_name' => 'Doe', 'email' => 'john.doe@foo.com' ], User::ROLE_USER );
        $this->member = $serviceManager->get('Application\UserService')->create( [ 'given_name' => 'Jane', 'family_name' => 'Doe', 'email' => 'jane.doe@foo.com' ], User::ROLE_USER );

        $this->organization = $serviceManager->get('People\OrganizationService')->createOrganization('Organization Name', $admin);
        $stream = $streamService->createStream($this->organization, "stream", $admin);

        $transactionManager->beginTransaction();
        try {
            $this->organization->addMember($this->owner, Organization::ROLE_MEMBER);
            $this->organization->addMember($this->member, Organization::ROLE_MEMBER);
            $this->task = Task::create($stream, 'Lorem Ipsum Sic Dolor Amit', $this->owner);
            $this->taskService->addTask($this->task);
            $transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $transactionManager->rollback();
            throw $e;
        }

        $this->task->addMember($this->owner, Task::ROLE_OWNER);
        $this->task->addMember($this->member, Task::ROLE_MEMBER);
        $this->task->open($this->owner);
        $this->task->execute($this->owner);
        $this->task->addEstimation(1500, $this->owner);
        $this->task->addEstimation(3100, $this->member);
        $this->task->complete($this->owner);
        $this->task->accept($this->owner, new \DateInterval('P7D'));

        $closeTimeboxDays = $this->organization->getParams()->get('assignment_of_shares_timebox')->format('%d') + 1;
		$dateAfterTimebox = new \DateTime();
        $dateAfterTimebox->modify('-'.$closeTimeboxDays.' day');
//        $this->task->updateMembersShare(new \DateTime('today'));

		$this->controller = $serviceManager->get("ControllerManager")->get('TaskManagement\Controller\Console\SharesClosing');

		$this->request = new ConsoleRequest();
	}


	public function testCloseTaskWhereNotAllUsersAssignedShares()
	{
        $members = $this->task->getMembers();
        $owner = array_shift($members);
        $member = array_shift($members);

        $this->task->assignShares([
            $this->owner->getId() => 0.33,
            $this->member->getId() => 0.67
        ], $this->owner);
die;
        ob_start();

		$this->controller->dispatch($this->request);

        $result = ob_get_clean();

        var_dump($result);

        $readModelTask = $this->taskService->findTask($this->task->getId());

        $this->assertEquals(Task::STATUS_CLOSED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_CLOSED, $readModelTask->getStatus());
        $this->assertEquals(true, $this->task->isSharesAssignmentCompleted());
	}
}
