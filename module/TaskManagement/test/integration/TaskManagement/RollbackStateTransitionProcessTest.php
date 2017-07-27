<?php
namespace TaskManagement;

use Application\Entity\User;
use People\Organization;
use Test\TestFixturesHelper;
use ZFX\Test\Authentication\OAuth2AdapterMock;

class RollbackStateTransitionProcessTest extends \BaseTaskProcessTest
{
    protected $admin;
    protected $organization;
    protected $owner;
    protected $member01;
    protected $member02;

    /**
     * @var Task $task
     */
    protected $task;


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
            $this->taskService->addTask($this->task);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }
	}

	public function testRevertOngoingToOpen() {
        $this->transactionManager->beginTransaction();
        try {

            $this->task->addMember($this->owner, Task::ROLE_MEMBER);
            $this->task->addMember($this->member01, Task::ROLE_MEMBER);
            $this->task->addMember($this->member02, Task::ROLE_MEMBER);
            $this->task->open($this->owner);
            $this->task->execute($this->owner);

            $this->transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

        $this->transactionManager->beginTransaction();
        try {
            $this->task->revertToOpen($this->owner);

            $this->transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }


        $readModelTask = $this->taskService->findTask($this->task->getId());
        $this->taskService->refreshEntity($readModelTask);

        $this->assertEquals(TASK::STATUS_OPEN, $this->task->getStatus());
        $this->assertEquals(TASK::STATUS_OPEN, $readModelTask->getStatus());

        foreach ($readModelTask->getMembers() as $member) {
            var_dump($member->getUser()->getFirstName());
        }

        $this->assertEquals(0, $readModelTask->countMembers());
        $this->assertCount(0, $this->task->getMembers());
    }

}
