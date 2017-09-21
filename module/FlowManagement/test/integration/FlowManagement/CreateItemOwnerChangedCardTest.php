<?php
namespace TaskManagement;

use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;

class CreateItemOwnerChangedCardTest extends \PHPUnit_Framework_TestCase
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

    public function testCreateItemOwnerChangedCard()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member, $member2]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member, $member2]);

        /*
        $mailbox = Mailbox::create();
        $mailbox->clean();

        // users get notified via mail
        $messages = $mailbox->getMessages();

        $this->assertEquals("Item 'Lorem Ipsum Sic Dolor Amit' was deleted", $messages[0]->subject);
        $this->assertEquals('<phil.toledo@ora.local>', $messages[0]->recipients[0]);
        */

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/owner");

        $this->assertEquals('200', $response->getStatusCode());
    }
}
