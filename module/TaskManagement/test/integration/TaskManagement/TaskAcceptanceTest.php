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

}
