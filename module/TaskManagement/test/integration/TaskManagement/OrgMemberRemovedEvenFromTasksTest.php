<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;

class OrgMemberRemovedEvenFromTasksTest extends WebTestCase
{
    private $stream;

    private $admin;
    private $member1;
    private $member2;

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

    public function testRemoveOrgMemberWhoIsTaskMember()
    {

        $tasks = [
//            $this->fixtures->createIdea('Lorem First Ipsum Sic Dolor Amit', $this->stream, $this->admin),
//            $this->fixtures->createOngoingTask('Lorem First Ipsum Sic Dolor Amit', $this->stream, $this->admin, [$this->member1]),
//            $this->fixtures->createCompletedTask('Lorem Second Ipsum Sic Dolor Amit', $this->stream, $this->admin, [$this->member1]),
//            $this->fixtures->createAcceptedTask('Lorem Third Ipsum Sic Dolor Amit', $this->stream, $this->admin, [$this->member1]),
            $this->fixtures->createAcceptedTaskWithShares('Lorem Fourth Ipsum Sic Dolor Amit', $this->stream, $this->admin, [$this->member1, $this->member2])
        ];

        $response = $this->client
            ->delete("/{$this->org->getId()}/people/members/{$this->member1->getId()}");

        $this->assertEquals('200', $response->getStatusCode());

        $response = $this->client
            ->get("/{$this->org->getId()}/task-management/tasks");

        $this->assertEquals('200', $response->getStatusCode());

        $tasks = json_decode($response->getContent(), true);
        $members = $tasks['_embedded']['ora:task'][0]['members'];
dump($members);
        $this->assertArrayNotHasKey($this->member1->getId(), $members);

    }


}
