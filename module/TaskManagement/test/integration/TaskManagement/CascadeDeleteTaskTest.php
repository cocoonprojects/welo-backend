<?php
namespace TaskManagement;

use FlowManagement\Entity\ItemDeletedCard;
use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;
use IntegrationTest\Bootstrap;

class CascadeDeleteTaskTest extends \PHPUnit_Framework_TestCase
{	
	protected $client;
    protected $fixtures;
    protected $flowService;

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';
        $serviceManager = Bootstrap::getServiceManager();

        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();

        $this->flowService = $serviceManager->get('FlowManagement\FlowService');

        $this->fixtures = new TestFixturesHelper($this->client->getServiceManager());

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

	public function testDeletedTask()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $mailbox = Mailbox::create();

        $res = $this->fixtures->createOrganization('my org', $admin, [$member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, $member);

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

        $this->assertEquals(1, $this->countItemDeletedFlowCard());
	}


    protected function countItemDeletedFlowCard() {
        //users get notified via flowcard
        $response = $this->client
                         ->get('/flow-management/cards?limit=1&offset=0');

        $flowCards = json_decode($response->getContent(), true);

        $count = 0;
        foreach ($flowCards as $idx => $flowCard) {
            if (get_class($flowCard) == ItemDeletedCard::class) {
                $count++;
            }
        }
        return $count;
    }
}
