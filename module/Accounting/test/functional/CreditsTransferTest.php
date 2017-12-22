<?php

namespace Accounting;

use Test\Mailbox;
use ZFX\Test\WebTestCase;

class CreditsTransferTest extends WebTestCase
{
    private $mailbox;
    
	public function setUp()
	{
	    parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
        $this->mailbox = Mailbox::create();
	}

    public function testIncomingTransfer()
	{
        $this->mailbox->clean();

	    $serviceManager = $this->client->getServiceManager();
	    $userService = $serviceManager->get('Application\UserService');
	    $orgService = $serviceManager->get('People\OrganizationService');
        $transactionManager = $serviceManager->get('prooph.event_store');

        $admin = $userService->findUserByEmail('bruce.wayne@ora.local');
        $member = $userService->findUserByEmail('phil.toledo@ora.local');

        $org = $orgService->createOrganization('my org', $admin);

        $transactionManager->beginTransaction();

        try {
            $org->addMember($member);
            $transactionManager->commit();
        } catch (\Exception $e) {
            $transactionManager->rollback();
            throw $e;
        }

        $transferData = [
            'amount' => '200',
            'description' => 'money!',
            'payer' => 'phil.toledo@ora.local',
        ];

        $this->client->post(
            "/{$org->getId()}/accounting/accounts/{$org->getAccountId()}/incoming-transfers",
            $transferData
        );

        $mail = $this->mailbox->getLastMessage();

        $this->assertContains(
            "Bruce transferred 200 credits from your account into 'my org' account",
            $mail
        );

        $this->client->setJWTToken(static::$tokens['phil.toledo@ora.local']);

        $response = $this->client->get('/flow-management/cards')->decodeJson();

        $flowCardData = array_shift($response['_embedded']['ora:flowcard'])['content']['description'];

        $this->assertEquals('The user Bruce subtracted 200 credits from your account', $flowCardData);
	}

    public function testOutgoingTransfer()
	{
        $this->mailbox->clean();

	    $serviceManager = $this->client->getServiceManager();
	    $userService = $serviceManager->get('Application\UserService');
	    $orgService = $serviceManager->get('People\OrganizationService');
        $transactionManager = $serviceManager->get('prooph.event_store');

        $admin = $userService->findUserByEmail('bruce.wayne@ora.local');
        $member = $userService->findUserByEmail('phil.toledo@ora.local');

        $org = $orgService->createOrganization('my org', $admin);

        $transactionManager->beginTransaction();

        try {
            $org->addMember($member);
            $transactionManager->commit();
        } catch (\Exception $e) {
            $transactionManager->rollback();
            throw $e;
        }

        $transferData = [
            'amount' => '200',
            'description' => 'money!',
            'payee' => 'phil.toledo@ora.local',
        ];

        $this->client->post(
            "/{$org->getId()}/accounting/accounts/{$org->getAccountId()}/outgoing-transfers",
            $transferData
        );

        $mail = $this->mailbox->getLastMessage();

        $this->assertContains(
            "Bruce transferred 200 credits in your account from 'my org' account",
            $mail
        );

        $this->client->setJWTToken(static::$tokens['phil.toledo@ora.local']);

        $response = $this->client->get('/flow-management/cards')->decodeJson();

        $flowCardData = array_shift($response['_embedded']['ora:flowcard'])['content']['description'];

        $this->assertEquals('The user Bruce took these credits from \'my org\' account', $flowCardData);
	}

}