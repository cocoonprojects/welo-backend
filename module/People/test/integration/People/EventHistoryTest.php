<?php
namespace TaskManagement;

use Application\Entity\User;
use Rhumsaa\Uuid\Uuid;
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
        $member2 = $this->fixtures->findUserByEmail('stephen.strange@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member, $member2]);

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

    public function testLogShouldNotBeBrokeByAccentNameMember()
    {
        $admin = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('stephen.strange@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member, $member2]);

        $this->client
            ->put("/{$res['org']->getId()}/people/members/{$member2->getId()}", [
                'memberId' => $member2->getId(),
                'orgId' => $res['org']->getId(),
                'role' => 'member'
        ]);

        $this->client
            ->delete("/{$res['org']->getId()}/people/members/{$member2->getId()}");

        $response = $this->client
            ->get("/{$res['org']->getId()}/people/members/{$member2->getId()}/history");
        $this->assertEquals('200', $response->getStatusCode());
    }

    public function testShouldShowMemberDeactivationEvent()
    {
        $admin = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $admin, [$member]);

        $response = $this->client
            ->put("/{$res['org']->getId()}/people/members/{$member->getId()}", [
                'active' => false
            ]);

        $response = $this->client
            ->get("/{$res['org']->getId()}/people/members/{$member->getId()}/history");
        $this->assertEquals('201', $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $deactEvent = $this->getDeactivationEvent($data);

        $this->assertNotEmpty($deactEvent);
        $this->assertEquals($member->getId(), $deactEvent['user']['id']);
        $this->assertEquals(0, $deactEvent['user']['active']);
    }


    protected function getDeactivationEvent($events) {
        $eventName = 'People\Event\OrganizationMemberActivationChanged';
        return $events[array_search($eventName, array_column($events, 'name'))];
    }
}
