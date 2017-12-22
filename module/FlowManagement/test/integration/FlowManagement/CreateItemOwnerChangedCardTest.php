<?php
namespace TaskManagement;

use ZFX\Test\WebTestCase;

class CreateItemOwnerChangedCardTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

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
        $this->assertEquals(1, $this->countOwnerRemovedFlowCard());
    }


    protected function countOwnerRemovedFlowCard()
    {
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
