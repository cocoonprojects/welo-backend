<?php

namespace TaskManagement;

use Test\Mailbox;
use ZFX\Test\WebTestCase;

class CascadeDeleteTaskTest extends WebTestCase
{	
    public function setUp()
    {
        parent::setUp();

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

        $this->assertCount(2, $messages);
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
                         ->get('/flow-management/cards?limit=10&offset=0');

        $flowCards = json_decode($response->getContent(), true);
        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'ItemDeleted') {
                $count++;
            }
        }
        return $count;
    }
}
