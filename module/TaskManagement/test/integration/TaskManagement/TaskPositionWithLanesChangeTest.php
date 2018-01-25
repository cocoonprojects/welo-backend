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


        // i task hanno tutti la priorità giusta inizialmente
        $data = $this->getTask($task1);
        $this->assertEquals(1, $data['position']);

        $data = $this->getTask($task2);
        $this->assertEquals(2, $data['position']);

        $data = $this->getTask($task3);
        $this->assertEquals(1, $data['position']);

        // aggiornando la lane la priorità viene aggiornata
        $this->client
             ->put(
                "/{$this->org->getId()}/task-management/tasks/{$task3->getId()}",
                ['lane' => (string) $this->lanes[0]['id'], 'subject' => '111', 'description' => 'gvnn']
             );

        $data = $this->getTask($task3);
        $this->assertEquals(3, $data['position']);

        //riportando l'item nella lane iniziale la priorità viene aggiornta
        $this->client
            ->put(
                "/{$this->org->getId()}/task-management/tasks/{$task3->getId()}",
                ['lane' => (string) $this->lanes[1]['id'], 'subject' => '222', 'description' => 'brazorf']
            );

        $data = $this->getTask($task3);
        $this->assertEquals(1, $data['position']);

        // se sposto un item, gli item successivi vengono aggiornati
        $this->client
            ->put(
                "/{$this->org->getId()}/task-management/tasks/{$task1->getId()}",
                ['lane' => (string) $this->lanes[1]['id'], 'subject' => '333', 'description' => 'ajeje']
            );

        $data = $this->getTask($task1);
        $this->assertEquals(2, $data['position']);

        $data = $this->getTask($task2);
        $this->assertEquals(1, $data['position']);
    }

    protected function getTask($task)
    {
        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks/{$task->getId()}");

        $data = json_decode($response->getContent(), true);

        return $data;
    }
}
