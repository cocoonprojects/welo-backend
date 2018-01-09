<?php

namespace TaskManagement;

use Rhumsaa\Uuid\Uuid;
use ZFX\Test\WebTestCase;

class TaskPositionWithLanesChangeTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $this->admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $this->member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $this->member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $this->lanes = [
            ['id' => Uuid::uuid4(), 'name' => 'prima lane'],
            ['id' => Uuid::uuid4(), 'name' => 'seconda lane'],
        ];

        $res = $this->fixtures->createOrganization(
            'my org',
            $this->admin,
            [],
            [$this->member1, $this->member2],
            $this->lanes
        );

        $this->org = $res['org'];
        $this->stream = $res['stream'];

    }

    public function testTasksPositionsInLane()
    {
        $task1 = $this->fixtures
                      ->createOpenTask(
                          'Lorem First Ipsum Sic Dolor Amit',
                          $this->stream,
                          $this->admin,
                          $this->lanes[0]['id']
                      );

        $task2 = $this->fixtures
                      ->createOpenTask(
                          'Lorem First Ipsum Sic Dolor Amit',
                          $this->stream,
                          $this->admin,
                          $this->lanes[0]['id']
                      );

        $task3 = $this->fixtures
                      ->createOpenTask(
                          'Lorem First Ipsum Sic Dolor Amit',
                          $this->stream,
                          $this->admin,
                          $this->lanes[1]['id']
                      );

        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks/{$task1->getId()}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['position']);

        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks/{$task2->getId()}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['position']);

        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks/{$task3->getId()}");

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['position']);


    }
}
