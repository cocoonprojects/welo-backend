<?php
namespace TaskManagement;

use Application\Entity\User;
use Rhumsaa\Uuid\Uuid;
use ZFX\Test\WebTestCase;

class MembersFindTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('mark.rogers@ora.local'));
    }

    public function testShouldOrderAdminMemberdContributors()
    {
        $admin = $this->fixtures->findUserByEmail('mark.rogers@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');
        $contributor = User::createUser(Uuid::uuid4(), 'brazorf@foo.com', 'bbbb', 'Doe');
        $contributor2 = User::createUser(Uuid::uuid4(), 'ajeje@foo.com', 'aaaa', 'Doe');

        $this->fixtures->saveUser($contributor);
        $this->fixtures->saveUser($contributor2);

        $res = $this->fixtures
                    ->createOrganization('my org', $admin, [$member, $member2], [$contributor, $contributor2]);

        $response = $this->client
                         ->get("/{$res['org']->getId()}/people/members");

        $data = json_decode($response->getContent(), true);


        $user = array_shift($data['_embedded']['ora:member']);

        $this->assertEquals('Mark Rogers', $user['createdBy']);
        $this->assertEquals('admin', $user['role']);

        $user = array_shift($data['_embedded']['ora:member']);

        $this->assertEquals('Paul Smith', $user['createdBy']);
        $this->assertEquals('member', $user['role']);
    }
}
