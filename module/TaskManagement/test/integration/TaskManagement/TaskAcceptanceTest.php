<?php
namespace TaskManagement;

use Rhumsaa\Uuid\Uuid;
use TaskManagement\Entity\Stream as ReadModelStream;
use TaskManagement\Entity\Task as ReadModelTask;
use TaskManagement\Entity\Vote;
use Test\Mailbox;
use ZFX\Test\WebTestCase;

class TaskAcceptanceTest extends WebTestCase
{	
	protected $client;
    protected $fixtures;
    protected $mailbox;

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $this->mailbox = Mailbox::create();
    }


	public function testAcceptTask()
    {
        $this->mailbox->clean();

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
                                 'value' => Vote::VOTE_FOR,
                                 'description' => 'lot of money for you!'
                             ]
                         );

        $this->assertEquals('201', $response->getStatusCode());


        $data = json_decode($response->getContent(), true);

        $this->assertEquals(TaskInterface::STATUS_ACCEPTED, $data['status']);
        $this->assertCount(1, $data['acceptances']);


        $messages = $this->mailbox->getMessages();
        $orgUserAcceptMessage = end($messages);
        $memberAcceptMessage = prev($messages);
        $memberAcceptMessageText = $this->mailbox->getMessage($memberAcceptMessage->id)->getBody(true);
        $orgUserAcceptMessageText = $this->mailbox->getMessage($orgUserAcceptMessage->id)->getBody(true);

        $this->assertEquals(1, $this->countTaskAcceptedEmails($admin->getEmail(), $messages));
        $this->assertEquals(1, $this->countTaskAcceptedEmails($member->getEmail(), $messages));
        $this->assertEquals('The "Lorem Ipsum Sic Dolor Amit" item has been accepted', $memberAcceptMessage->subject);
        $this->assertEquals('The "Lorem Ipsum Sic Dolor Amit" item has been accepted', $orgUserAcceptMessage->subject);
        $this->assertEquals('<bruce.wayne@ora.local>', $memberAcceptMessage->recipients[0]);
        $this->assertEquals('<phil.toledo@ora.local>', $orgUserAcceptMessage->recipients[0]);
        $this->assertContains('<td>lot of money for you!</td>', $memberAcceptMessageText);
        $this->assertContains('time to assign your shares', $memberAcceptMessageText);
        $this->assertContains('<td>lot of money for you!</td>', $orgUserAcceptMessageText);
        $this->assertNotContains('time to assign your shares', $orgUserAcceptMessageText);
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

	public function testFindCompletedTasksBeforeTimebox()
    {
        $org = new \People\Entity\Organization(Uuid::uuid4());
        $org->setCreatedAt(new \DateTime());
        $org->setMostRecentEditAt(new \DateTime());

        $stream = new ReadModelStream(Uuid::uuid4(), $org);
        $stream->setCreatedAt(new \DateTime());
        $stream->setMostRecentEditAt(new \DateTime());

        $task = new ReadModelTask(Uuid::uuid4(), $stream);
        $task->setSubject('Amazing task');
        $task->setStatus(TaskInterface::STATUS_COMPLETED);
        $task->setCompletedAt(new \DateTime());
        $task->setCreatedAt(new \DateTime());
        $task->setMostRecentEditAt(new \DateTime());

        $task2 = new ReadModelTask(Uuid::uuid4(), $stream);
        $task2->setSubject('Amazing old task');
        $task2->setStatus(TaskInterface::STATUS_COMPLETED);
        $task2->setCompletedAt(new \DateTime('-8 days'));
        $task2->setCreatedAt(new \DateTime());
        $task2->setMostRecentEditAt(new \DateTime());

        $task3 = new ReadModelTask(Uuid::uuid4(), $stream);
        $task3->setSubject('Amazing very old task');
        $task3->setStatus(TaskInterface::STATUS_COMPLETED);
        $task3->setCompletedAt(new \DateTime('-1 month'));
        $task3->setCreatedAt(new \DateTime());
        $task3->setMostRecentEditAt(new \DateTime());


        $em = $this->client->getServiceManager()->get('doctrine.entitymanager.orm_default');
        $em->persist($org);
        $em->persist($stream);
        $em->persist($task);
        $em->persist($task2);
        $em->persist($task3);
        $em->flush();

        $this->taskService = $this->client->getServiceManager()->get('TaskManagement\TaskService');
        $tasks = $this->taskService->findItemsCompletedBefore(new \DateInterval('P7D'), $org->getId());

        $this->assertCount(2, $tasks);
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


    protected function countTaskAcceptedEmails($account ,$messages) {
        $count = 0;
        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'item has been accepted') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }

}
