<?php

namespace TaskManagement;

use BaseTaskProcessTest;
use Application\Entity\User;
use People\Organization;
use People\Entity\OrganizationEntity;
use Test\Mailbox;
use Test\TestFixturesHelper;
use Zend\Console\Request as ConsoleRequest;
use TaskManagement\Service\MailService;

/**
 * FIXME: gira singolarmente ma non in gruppo
 */
class ConsoleSharesClosingProcess extends BaseTaskProcessTest
{
	protected $admin;
	protected $organization;
	protected $owner;
	protected $member01;
	protected $member02;
	protected $task;
	protected $mailbox;

	protected function setUp()
	{
        $this->admin = $this->createUser(['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => TestFixturesHelper::generateRandomEmail()], User::ROLE_ADMIN);
        $this->owner = $this->createUser([ 'given_name' => 'John', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail() ], User::ROLE_USER );
        $this->member01 = $this->createUser([ 'given_name' => 'Jane', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail() ], User::ROLE_USER );
        $this->member02 = $this->createUser([ 'given_name' => 'Jack', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail() ], User::ROLE_USER );

        $this->organization = $this->createOrganization(TestFixturesHelper::generateRandomName(), $this->admin);
        $stream = $this->createStream(TestFixturesHelper::generateRandomName(), $this->organization, $this->admin, $this->serviceManager);


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

		$this->controller = $this->serviceManager->get("ControllerManager")->get('TaskManagement\Controller\Console\SharesClosing');

        $task = $this->taskService->findTask($this->task->getId());
        $this->taskService->refreshEntity($task);

		$this->request = new ConsoleRequest();

        $this->mailbox = Mailbox::create();
    }

    public function testCloseTaskWhereNotAllUsersAssignedShares()
	{
        $this->setSharesTimebox($this->organization, 0);

        $this->mailbox->clean();

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
		$this->controller->closeAction();
        $result = ob_get_clean();

        $mail = $this->mailbox->getLastMessage();

        $this->assertEquals(Task::STATUS_CLOSED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_CLOSED, $readModelTask->getStatus());
        $this->assertContains('found 1 accepted items to process', $result);
        $this->assertContains('closing task '.$this->task->getId(), $result);

        $this->assertContains(
            $this->task->getId(),
            $mail
        );

        $this->assertContains(
            "in which you are the owner has been closed, 2 members on 3 assigned shares for it",
            $mail
        );

	}

	public function testCannotCloseTaskWhereNotMinimumSharesReached()
	{
        $this->setSharesTimebox($this->organization, 0);

        $this->mailbox->clean();

        $this->organization = $this->organizationService->getAggregateRoot($this->organization->getId());

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
		$this->controller->closeAction();
        $result = ob_get_clean();

        $mail = $this->mailbox->getLastMessage();

        $this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
        $this->assertContains('found 1 accepted items to process', $result);
        $this->assertContains('Not enough shares to close the task '.$this->task->getId(), $result);

        $this->assertContains(
            $this->task->getId(),
            $mail
        );
        $this->assertContains(
            "in which you are the owner has NOT been closed automatically, only 1 members on 3 assigned shares for it",
            $mail
        );

	}

	public function testCannotCloseTaskWhenNoTimeboxReached()
	{
        $this->setSharesTimebox($this->organization, 10);

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
		$this->controller->closeAction();
        $result = ob_get_clean();


        $this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
        $this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
        $this->assertContains('found 0 accepted items to process in '.$this->organization->getId(), $result);
	}

    protected function setSharesTimebox($organization, $days)
    {
        $orgData = $organization->getParams();
        $orgData->params['assignment_of_shares_timebox'] = new \DateInterval("P{$days}D");

        $this->transactionManager->beginTransaction();
        try {
            $organization->setParams($orgData->params, $this->admin);
            $this->transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }
    }
}

