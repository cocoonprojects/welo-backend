<?php

namespace TaskManagement;

use Test\Mailbox;
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
            [$this->member2],
            [$this->member1]
        );

        $this->org = $res['org'];
        $this->stream = $res['stream'];

    }

    public function testDeactivateAndReactivateOrgMember()
    {
        $mailbox = Mailbox::create();
        $mailbox->clean();

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

        $messages = $mailbox->getMessages();
        $this->assertEquals(2, $this->countActivationEmails($this->member2->getEmail(), $messages));
        $this->assertEquals(2, $this->countDeactivationEmails($this->member2->getEmail(), $messages));
    }


    protected function countActivationEmails($account ,$messages) {
        $count = 0;
        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'has been activated') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }


    protected function countDeactivationEmails($account ,$messages) {
        $count = 0;
        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'has been deactivated') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }

}
