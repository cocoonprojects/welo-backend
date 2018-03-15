<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;

class RemoveItemMemberCardTest extends WebTestCase
{
    protected $client;
    protected $fixtures;
    protected $flowService;

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

    public function testRemoveMemberCard()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $owner = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$owner, $member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Remove Member Card', $res['stream'], $owner, [$member]);

        $response = $this->client
            ->delete("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/members/{$member->getId()}");

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(1, $this->countOwnerRemovedFlowCard($task->getId()));

        $this->client->setJWTToken($this->fixtures->getJWTToken($member->getEmail()));
        $this->assertEquals(1, $this->countOwnerRemovedFlowCard($task->getId()));

        $this->client->setJWTToken($this->fixtures->getJWTToken($owner->getEmail()));
        $this->assertEquals(1, $this->countOwnerRemovedFlowCard($task->getId()));
    }


    protected function countOwnerRemovedFlowCard($taskId)
    {
        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=300&offset=0');

        $flowCards = json_decode($response->getContent(), true);

        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'ItemMemberRemoved' &&
                $flowCard['content']['actions']['primary']['itemId'] == $taskId
                ) {
                $count++;
            }
        }

        return $count;
    }
}
