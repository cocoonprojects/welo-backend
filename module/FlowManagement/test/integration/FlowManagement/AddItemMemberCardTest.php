<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;
use Test\Mailbox;

class AddItemMemberCardTest extends WebTestCase
{
    protected $client;
    protected $fixtures;
    protected $flowService;

    protected $mailbox;

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $this->mailbox = Mailbox::create();
    }

    public function testAddMemberCard()
    {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $owner = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$owner, $member]);

        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Add Member Card', $res['stream'], $owner, [$member]);

        $this->mailbox->clean();

        $response = $this->client
            ->post("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/members", []);
        $this->assertEquals('201', $response->getStatusCode());


        $this->client->setJWTToken($this->fixtures->getJWTToken($owner->getEmail()));
        $this->assertEquals(2, $this->countMemberAddedFlowCard($task->getId()));

        $emails = $this->mailbox->getMessages();

        $this->assertNotEmpty($emails);
        $this->assertEquals(1, count($emails));
        $this->assertContains($task->getSubject(), $emails[0]->subject);
    }


    protected function countMemberAddedFlowCard($taskId)
    {
        //users get notified via flowcard
        $response = $this->client
                         ->get('/flow-management/cards?limit=10&offset=0');

        $flowCards = json_decode($response->getContent(), true);

        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'ItemMemberAdded' &&
                $flowCard['content']['actions']['primary']['itemId'] == $taskId
                ) {
                $count++;
            }
        }

        return $count;
    }
}
