<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;
use Test\Mailbox;

class RollbackStateTransitionProcessTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

    public function testRevertFromRejectedToIdea()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createRejectedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToIdea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);

    }

    public function testRevertFromOngoingToOpen()
    {
        $mailbox = Mailbox::create();
        $mailbox->clean();

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1], [$member2]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToOpen" ]
            );
        $task = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $task['status']);
        $this->assertEquals(1, $task['position']);
        $this->assertEmpty($task['members']);

        $messages = $mailbox->getMessages();
        $this->assertEquals(1, $this->countBackToOpenEmails($admin->getEmail(), $messages));
        $this->assertEquals(1, $this->countBackToOpenEmails($member1->getEmail(), $messages));
        $this->assertEquals(0, $this->countBackToOpenEmails($member2->getEmail(), $messages));
    }

    public function testRevertFromOpenToIdea() {

        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [], [$member1, $member2]);
        $task = $this->fixtures->createOpenTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin);

        $this->assertEquals(TASK::STATUS_OPEN, $task->getStatus());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_OPEN, $responseTask['status']);
        $this->assertNotEmpty($responseTask['approvals']);


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToIdea" ]
            );

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_IDEA, $responseTask['status']);
        $this->assertEmpty($responseTask['approvals']);
    }

    public function testRevertFromCompletedToOngoing()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createCompletedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToOngoing" ]
        );

        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_ONGOING, $responseTask['status']);
        $this->assertEmpty($responseTask['acceptances']);

        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/history");

        $responseTask = json_decode($response->getContent(), true);

        $events = array_column($responseTask, 'name');

        $this->assertContains('TaskRevertedToOngoing', $events);
    }

    public function testRevertFromAcceptedToCompleted()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createAcceptedTaskWithShares('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, [$member1, $member2]);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToCompleted" ]
            );

        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_COMPLETED, $responseTask['status']);

        $this->assertEmpty($responseTask['acceptances']);
        $this->assertArrayNotHasKey('shares', array_shift($responseTask['members']));
        $this->assertArrayNotHasKey('shares', array_shift($responseTask['members']));
        $this->assertArrayNotHasKey('shares', array_shift($responseTask['members']));
    }

    public function testRevertFromClosedToAccepted()
    {
        $admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member1, $member2]);
        $task = $this->fixtures->createClosedTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $admin, $member1, $member2);

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "backToAccepted" ]
            );

        $responseTask = json_decode($response->getContent(), true);

        $this->assertEquals('200', $response->getStatusCode());
        $this->assertEquals(TASK::STATUS_ACCEPTED, $responseTask['status']);

        $this->assertArrayNotHasKey('credits', array_shift($responseTask['members']));
        $this->assertArrayNotHasKey('credits', array_shift($responseTask['members']));
        $this->assertArrayNotHasKey('credits', array_shift($responseTask['members']));

        $response = $this->client->get("/{$res['org']->getId()}/accounting/personal-statement");
        $response = json_decode($response->getContent(), true);

        //transfer is reverted
        $this->assertEquals(-33.33, $response['_embedded']['transactions'][0]['amount'], '', 0.01);

        // total credits are reverted
        $response = $this->client->get("/{$res['org']->getId()}/accounting/members/{$member1->getId()}");
        $response = json_decode($response->getContent(), true);

        $expected = [
            'balance' => 0,
            'total' => 0,
            'last3M' => 0,
            'last6M' => 0,
            'last1Y' => 0,
        ];

        $this->assertEquals($expected, $response);
    }


    protected function countBackToOpenEmails($account ,$messages) {
        $count = 0;

        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'was reverted back to Open state') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }

}
