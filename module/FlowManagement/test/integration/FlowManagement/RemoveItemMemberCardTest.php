<?php
namespace TaskManagement;

use FlowManagement\Entity\ItemOwnerChangedCard;
use IntegrationTest\Bootstrap;
use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;

class RemoveItemMemberCardTest extends \PHPUnit_Framework_TestCase
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

    public function testRemoveMemberCard()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $oldOwner = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $newOwner = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$oldOwner, $newOwner]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $oldOwner, [$admin, $newOwner]);

        $response = $this->client
            ->delete("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/members");

        $this->assertEquals('200', $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

//        $this->assertEquals('member', $data['members'][$oldOwner->getId()]['role']);
//        $this->assertEquals('owner', $data['members'][$newOwner->getId()]['role']);
//
//        $this->assertEquals(1, $this->countOwnerChangedFlowCard($oldOwner));
//        $this->assertEquals(1, $this->countOwnerChangedFlowCard($newOwner));
//        $this->assertEquals(1, $this->countOwnerChangedFlowCard($admin));

    }


    protected function countOwnerChangedFlowCard($member) {
        $flowCards = $this->flowService->findFlowCards($member, null, null, null);
        $count = 0;
        foreach ($flowCards as $idx => $flowCard) {
            if (get_class($flowCard) == ItemOwnerChangedCard::class) {
                $count++;
            }
        }
        return $count;
    }
}
