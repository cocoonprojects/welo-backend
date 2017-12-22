<?php
namespace TaskManagement;

use ZFX\Test\WebTestCase;

class EventHistoryTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('mark.rogers@ora.local'));
    }

    public function testShouldShowEventLogForMembers()
    {
        $admin = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member]);

        $this->client
            ->put("/{$res['org']->getId()}/people/members/{$member->getId()}", [
                'memberId' => $member->getId(),
                'orgId' => $res['org']->getId(),
                'role' => 'member'
        ]);

        $this->client
            ->delete("/{$res['org']->getId()}/people/members/{$member->getId()}");

        $response = $this->client
            ->get("/{$res['org']->getId()}/people/members/{$member->getId()}/history");
        $this->assertEquals('200', $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(3, $data);
        $this->assertEquals(["id", "name", "on", "user" ], array_keys($data[0]));
    }
}
