<?php
namespace TaskManagement;

use PHPUnit_Framework_TestCase;
use Test\TestFixturesHelper;
use Test\ZFHttpClient;

class RollbackStateTransitionProcessTest extends PHPUnit_Framework_TestCase
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

    public function testRevertFromRejectedToIdea()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createRejectedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToIdea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);

    }

    public function testRevertFromOngoingToOpen() {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToOpen" ]
            );
        $task = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $task['status']);
        $this->assertEmpty($task['members']);
    }

    public function testRevertFromOpenToIdea() {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [], [$member1, $member2]);
        $task = $this->fixtures->createOpenTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin);

        $this->assertEquals(TASK::STATUS_OPEN, $task->getStatus());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $responseTask['status']);
        $this->assertNotEmpty($responseTask['approvals']);


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToIdea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);
    }

    public function testRevertFromCompletedToOngoing()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createCompletedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToOngoing" ]
        );

        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_ONGOING, $responseTask['status']);
        $this->assertEmpty($responseTask['acceptances']);

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/history");

        $responseTask = json_decode($response->getContent(), true);

        $events = array_column($responseTask, 'name');
        $event = array_pop($events);

        $this->assertEquals('TaskRevertedToOngoing', $event);
    }

}
