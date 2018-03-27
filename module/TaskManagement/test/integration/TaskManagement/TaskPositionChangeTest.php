<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;

class TaskPositionChangeTest extends WebTestCase
{
    protected $stream;
    protected $org;
    protected $admin;
    protected $member1;
    protected $member2;

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $this->admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $this->member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $this->member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization(
            'my org',
            $this->admin,
            [],
            [$this->member1, $this->member2]
        );

        $this->org = $res['org'];
        $this->stream = $res['stream'];

    }


    public function testChangeOpenTasksPositions()
    {

        $tasks = [
            $this->fixtures->createOpenTask('Lorem First Ipsum Sic Dolor Amit', $this->stream, $this->admin),
            $this->fixtures->createOpenTask('Lorem Second Ipsum Sic Dolor Amit', $this->stream, $this->admin),
            $this->fixtures->createOpenTask('Lorem Third Ipsum Sic Dolor Amit', $this->stream, $this->admin)
        ];

        $response = $this->client
            ->post("/{$this->org->getId()}/task-management/tasks/positions", [
                $tasks[0]->getId() => 3,
                $tasks[1]->getId() => 2,
                $tasks[2]->getId() => 1
            ]);

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks?orderBy=position&orderType=desc");

        $this->assertEquals('200', $response->getStatusCode());

        $tasks = json_decode($response->getContent(), true);

        $this->assertEquals(3, $tasks['_embedded']['ora:task'][0]['position']);
        $this->assertEquals(2, $tasks['_embedded']['ora:task'][1]['position']);
        $this->assertEquals(1, $tasks['_embedded']['ora:task'][2]['position']);
    }


    public function testOpeningATaskGivesItTheRightPosition()
    {
        $task = $this->fixtures->createIdea(
            'Lorem First Ipsum Sic Dolor Amit',
            $this->stream,
            $this->admin
        );

        $response = $this->client
            ->post(
                "/{$this->org->getId()}/task-management/tasks/{$task->getId()}/approvals",
                [ "value" => 1, "description" => "I approve it" ]
            );
        $this->assertEquals(201, $response->getStatusCode());

        $response = $this->client
            ->get(
                "/{$this->org->getId()}/task-management/tasks/{$task->getId()}"
            );
        $this->assertEquals(200, $response->getStatusCode());

        $task = json_decode($response->getContent());
        $this->assertEquals(1, $task->position);

    }


    public function testFixOpenTasksPositionsWhenTaskStarts()
    {

        $tasks = [
            $this->fixtures->createOpenTask('Lorem First Ipsum Sic Dolor Amit', $this->stream, $this->admin),
            $this->fixtures->createOpenTask('Lorem Second Ipsum Sic Dolor Amit', $this->stream, $this->admin),
            $this->fixtures->createOpenTask('Lorem Third Ipsum Sic Dolor Amit', $this->stream, $this->admin)
        ];

        $orgId = $this->org->getId();
        $taskId = $tasks[0]->getId();

        $response = $this->client
            ->post(
                "/{$orgId}/task-management/tasks/{$taskId}/transitions",
                [ "action" => "execute" ]
            );
        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$orgId}/task-management/tasks?status=10&orderBy=position&orderType=asc");
        $this->assertEquals('200', $response->getStatusCode());

        $tasks = json_decode($response->getContent(), true);

        $this->assertEquals(2, $tasks['count']);
        $this->assertEquals(1, $tasks['_embedded']['ora:task'][0]['position']);
        $this->assertEquals(2, $tasks['_embedded']['ora:task'][1]['position']);
    }
}
