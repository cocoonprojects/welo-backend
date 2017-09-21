<?php
namespace TaskManagement;

use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;

class CascadeDeleteTaskTest extends \PHPUnit_Framework_TestCase
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

	public function testDeletedTask()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $mailbox = Mailbox::create();

        $res = $this->fixtures->createOrganization('my org', $admin, [$member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member]);

        $mailbox->clean();

        $response = $this->client
                         ->delete("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");

        $this->assertEquals('200', $response->getStatusCode());

        // users get notified via mail
        $messages = $mailbox->getMessages();

        $this->assertEquals("Item 'Lorem Ipsum Sic Dolor Amit' was deleted", $messages[0]->subject);
        $this->assertEquals('<phil.toledo@ora.local>', $messages[0]->recipients[0]);
        $this->assertEquals('<bruce.wayne@ora.local>', $messages[1]->recipients[0]);

        $response = $this->client
                         ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");

        $this->assertEquals('404', $response->getStatusCode());

        //users get notified via flowcard
        $response = $this->client
                         ->get('/flow-management/cards?limit=1&offset=0');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Item deleted', current($data['_embedded']['ora:flowcard'])['title']);
	}
}
