<?php
namespace TaskManagement;

use FlowManagement\Entity\ItemOwnerChangedCard;
use IntegrationTest\Bootstrap;
use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;

class CreateItemOwnerChangedCardTest extends \PHPUnit_Framework_TestCase
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

    public function testCreateItemOwnerChangedCard()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $owner = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$owner, $member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $owner, [$admin, $member]);

        $response = $this->client
            ->delete("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/members", ['memberId' => $member->getId()]);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(1, $this->countOwnerRemovedFlowCard($owner));
    }


    protected function countOwnerRemovedFlowCard($member) {
        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0');

        $flowCards = json_decode($response->getContent(), true);
        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'ItemMemberRemoved') {
                $count++;
            }
        }
        return $count;
    }
}
