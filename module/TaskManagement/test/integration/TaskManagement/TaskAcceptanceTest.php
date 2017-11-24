<?php
namespace TaskManagement;

use People\Organization;
use TaskManagement\Entity\Vote;
use Test\Mailbox;
use Test\TestFixturesHelper;
use Test\ZFHttpClient;

class TaskAcceptanceTest extends \PHPUnit_Framework_TestCase
{	
	protected $client;
    protected $fixtures;

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';
        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();

        $this->fixtures = new TestFixturesHelper($this->client->getServiceManager());

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }


	public function testReopenTask()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [], [$member]);
        $task = $this->fixtures->createCompletedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, $member);

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(TaskInterface::STATUS_COMPLETED, $data['status']);
        $this->assertCount(0, $data['acceptances']);

        $response = $this->client
                         ->post(
                             "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/acceptances",
                             [
                                 'value' => Vote::VOTE_AGAINST,
                                 'description' => 'no money for you!'
                             ]
                         );

        $this->assertEquals('201', $response->getStatusCode());


        $data = json_decode($response->getContent(), true);

        $this->assertEquals(TaskInterface::STATUS_ONGOING, $data['status']);
        $this->assertCount(0, $data['acceptances']);
	}

    /**
     * @see https://www.pivotaltracker.com/story/show/152206793
     */
	public function testAcceptanceRegression()
    {
        $this->transactionManager = $this->client->getServiceManager()->get('prooph.event_store');
        $this->taskService = $this->client->getServiceManager()->get('TaskManagement\TaskService');

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('paul.smith@ora.local');
        $member2 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member3 = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member4 = $this->fixtures->findUserByEmail('spidey.web@dailybugle.local');
        $member5 = $this->fixtures->findUserByEmail('dianaprince@ww.com');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2, $member3, $member4, $member5]);

        $task = $this->fixtures->createIdea('subject', $res['stream'], $admin);

        $this->transactionManager->beginTransaction();

        try {

            $task->addApproval(Vote::VOTE_FOR, $admin, 'ok');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addApproval(Vote::VOTE_FOR, $member1, 'ok1');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addApproval(Vote::VOTE_FOR, $member2, 'ok1');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addApproval(Vote::VOTE_FOR, $member3, 'ok1');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->assertEquals(Task::STATUS_OPEN, $task->getStatus());

        $this->transactionManager->beginTransaction();

        try {

            $task->execute($member1);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addMember($member2);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addMember($admin);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addEstimation(500, $member1);
            $task->addEstimation(200, $member2);
            $task->addEstimation(-1, $admin);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->complete($member1);

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->assertEquals(Task::STATUS_COMPLETED, $task->getStatus());


        $this->transactionManager->beginTransaction();

        try {

            $task->addAcceptance(Vote::VOTE_FOR, $member1, 'bella li');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->assertEquals(Task::STATUS_COMPLETED, $task->getStatus());


        $this->transactionManager->beginTransaction();

        try {

            $task->addAcceptance(Vote::VOTE_FOR, $member2, 'bella la');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->transactionManager->beginTransaction();

        try {

            $task->addAcceptance(Vote::VOTE_FOR, $admin, 'bella lu');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->assertEquals(Task::STATUS_COMPLETED, $task->getStatus());

        $this->transactionManager->beginTransaction();

        try {

            $task->addAcceptance(Vote::VOTE_FOR, $member3, 'bella lu');

            $this->transactionManager->commit();
        }catch (\Exception $e) {
            dump($e->getMessage());

            $this->transactionManager->rollback();

            throw $e;
        }

        $this->assertEquals(Task::STATUS_ACCEPTED, $task->getStatus());
    }

}
