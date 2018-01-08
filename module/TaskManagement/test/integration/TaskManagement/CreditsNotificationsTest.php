<?php

namespace TaskManagement;

use Test\Mailbox;
use ZFX\Test\WebTestCase;

class CreditsNotificationsTest extends WebTestCase
{

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

    public function testCreditsTransferNotifications()
    {
        $accountService = $this->client
                               ->getServiceManager()
                               ->get('Accounting\CreditsAccountsService');

        $owner = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $owner, [$member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $owner, [$member]);

        $mailbox = Mailbox::create();
        $mailbox->clean();

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/deposits",
                [
                    'amount' => 500,
                    'description' => "Base deposit"
                ]
            );

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/outgoing-transfers",
                [
                    'amount' => 150,
                    'description' => "Beccate sti du crediti",
                    'payee' => "phil.toledo@ora.local"
                ]
            );

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/outgoing-transfers",
                [
                    'amount' => 123,
                    'description' => "E anche sti altri due",
                    'payee' => "phil.toledo@ora.local"
                ]
            );

        $this->assertEquals(201, $response->getStatusCode());

        // users get notified via mail
        $messages = $mailbox->getMessages();
        $this->assertEquals(2, $this->countCreditsAddedEmails($member->getEmail(), $messages));


        $this->client->setJWTToken($this->fixtures->getJWTToken('phil.toledo@ora.local'));

        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0');
        $flowCards = json_decode($response->getContent(), true);

        $this->assertEquals(2, $this->countCreditsAddedFlowCard($flowCards));


        $userAccount = $accountService->findPersonalAccount($member, $res['org']);
        $response = $this->client
            ->get(
                "/{$res['org']->getId()}/accounting/accounts/{$userAccount->getId()}"
            );
        $balance = json_decode($response->getContent());
        $this->assertEquals(273, $balance->balance);
    }


    public function testCreditsSharesNotifications()
    {
        $owner = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $member2 = $this->fixtures->findUserByEmail('mark.rogers@ora.local');

        $res = $this->fixtures->createOrganization('my org 2', $owner, [], [$member, $member2]);
        $task = $this->fixtures->createAcceptedTask('Credits Share Notifications item', $res['stream'], $owner, [$member, $member2]);

        $mailbox = Mailbox::create();
        $mailbox->clean();


        $transactionManager = $this->client->getServiceManager()->get('prooph.event_store');
        $transactionManager->beginTransaction();
        try {
            $task->assignShares([
                $owner->getId() => '1.0',
                $member->getId() => '0',
                $member2->getId() => '0',
            ], $owner);
            $task->assignShares([
                $owner->getId() => '0.70',
                $member->getId() => '0.30',
                $member2->getId() => '0',
            ], $member);
            $task->assignShares([
                $owner->getId() => '0.20',
                $member->getId() => '0.60',
                $member2->getId() => '0.20',
            ], $member2);
            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $transactionManager->rollback();
            throw $e;
        }


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "close" ]
            );
        $this->assertEquals('200', $response->getStatusCode());


        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $this->assertEquals('200', $response->getStatusCode());


        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0');
        $flowCards = json_decode($response->getContent(), true);
        $this->assertEquals(1, $this->countCreditsAddedFlowCard($flowCards));

        $messages = $mailbox->getMessages();
        $this->assertEquals(1, $this->countCreditsAddedEmails($member->getEmail(), $messages));




    }


    protected function countCreditsAddedFlowCard($flowCards) {
        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'CreditsAdded') {
                $count++;
            }
        }
        return $count;
    }


    protected function countCreditsAddedEmails($account ,$messages) {
        $count = 0;
        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'credits transferred in your account from the') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }


}
