<?php

namespace TaskManagement;

use ZFX\Test\WebTestCase;

class OrgMemberActivationTest extends WebTestCase
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

    public function testDeactivateAndReactivateOrgMember()
    {

        $response = $this->client
            ->put("/{$this->org->getId()}/people/members/{$this->member1->getId()}", [
                'active' => false
            ]);
        $membership = json_decode($response->getContent());

        $this->assertEquals('201', $response->getStatusCode());
        $this->assertFalse($membership->active);


        $response = $this->client
            ->put("/{$this->org->getId()}/people/members/{$this->member1->getId()}", [
                'active' => true
            ]);
        $membership = json_decode($response->getContent());

        $this->assertEquals('201', $response->getStatusCode());
        $this->assertTrue($membership->active);
    }


}
