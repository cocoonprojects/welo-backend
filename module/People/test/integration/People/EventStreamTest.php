<?php
namespace TaskManagement;

use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;

class EventStreamTest extends \PHPUnit_Framework_TestCase
{
    protected $client;
    protected $fixtures;

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';

        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();

        $this->fixtures = new TestFixturesHelper($this->client->getServiceManager());

        $this->client->setJWTToken($this->fixtures->getJWTToken('mark.rogers@ora.local'));
    }

    public function testEventStreamTask()
    {
        $admin = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member]);

        $response = $this->client
            ->get("/{$res['org']->getId()}/people/members/{$member->getId()}/history");

        $this->assertEquals('200', $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertEquals(["id", "name", "on", "user" ], array_keys($data[0]));
    }
}
