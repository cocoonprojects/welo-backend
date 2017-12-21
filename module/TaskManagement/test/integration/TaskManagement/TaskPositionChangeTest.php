<?php
namespace TaskManagement;

use PHPUnit_Framework_TestCase;
use Test\TestFixturesHelper;
use Test\ZFHttpClient;

class TaskPositionChangeTest extends PHPUnit_Framework_TestCase
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

    public function testChangeOpenTasksPositions()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [], [$member1, $member2]);
        $tasks = [
            $this->fixtures->createOpenTask('Lorem First Ipsum Sic Dolor Amit', $res['stream'], $admin),
            $this->fixtures->createOpenTask('Lorem Second Ipsum Sic Dolor Amit', $res['stream'], $admin),
            $this->fixtures->createOpenTask('Lorem Third Ipsum Sic Dolor Amit', $res['stream'], $admin)
        ];

        $response = $this->client
            ->post("/{$res['org']->getId()}/task-management/tasks/positions", [
                $tasks[0]->getId() => 3,
                $tasks[1]->getId() => 2,
                $tasks[2]->getId() => 1
            ]);
        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks?orderBy=position&orderType=asc");

        $this->assertEquals('200', $response->getStatusCode());

        $tasks = json_decode($response->getContent(), true);

        $this->assertEquals(3, $tasks['_embedded']['ora:task'][0]['position']);
        $this->assertEquals(2, $tasks['_embedded']['ora:task'][1]['position']);
        $this->assertEquals(1, $tasks['_embedded']['ora:task'][2]['position']);
    }

}
