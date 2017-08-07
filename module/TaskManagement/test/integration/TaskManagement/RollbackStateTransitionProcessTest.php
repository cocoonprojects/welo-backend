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


	public function testRevertOngoingToOpen() {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createTask(Task::STATUS_ONGOING, 'Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "open" ]
            );
        $task = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $task['status']);
        $this->assertEmpty($task['members']);
    }


	public function testRevertOpenToIdea() {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createTask(Task::STATUS_OPEN, 'Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);


        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $responseTask['status']);
        $this->assertNotEmpty($responseTask['approvals']);


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "idea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());


        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);
    }

    public function testRevertRejectedToIdea()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createRejectedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "idea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);

    }

}
