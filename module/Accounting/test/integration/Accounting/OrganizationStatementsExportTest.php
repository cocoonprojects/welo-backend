<?php

namespace TaskManagement;

use Test\Mailbox;
use ZFX\Test\WebTestCase;

class OrganizationStatementsExportTest extends WebTestCase
{

    public function setUp()
    {
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

    public function testOrganizationStatementsExport()
    {
        $accountService = $this->client
                               ->getServiceManager()
                               ->get('Accounting\CreditsAccountsService');

        $owner = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $owner, [$member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $owner, [$member]);

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
                    'description' => "Beccate sti du; crediti",
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



        $this->client->setJWTToken($this->fixtures->getJWTToken('phil.toledo@ora.local'));

        //users get notified via flowcard
        $response = $this->client
            ->get("/{$res['org']->getId()}/accounting/organization-statement-export?limit=10&offset=0");

        $lines = explode(PHP_EOL, $response->getContent());
        $actual = [];
        foreach ($lines as $line) {
            $actual[] = str_getcsv($line, ';');
        }

        $this->assertEquals('my org',                   $actual[1][1]);
        $this->assertEquals('Phil Toledo',              $actual[1][2]);
        $this->assertEquals('-123,0',                   $actual[1][3]);
        $this->assertEquals('227,0',                    $actual[1][4]);
        $this->assertEquals('E anche sti altri due',    $actual[1][5]);

        $this->assertEquals('my org',                   $actual[2][1]);
        $this->assertEquals('Phil Toledo',              $actual[2][2]);
        $this->assertEquals('-150,0',                   $actual[2][3]);
        $this->assertEquals('350,0',                    $actual[2][4]);
        $this->assertEquals('Beccate sti du; crediti',  $actual[2][5]);

        $this->assertEquals('',                         $actual[3][1]);
        $this->assertEquals('my org',                   $actual[3][2]);
        $this->assertEquals('500,0',                    $actual[3][3]);
        $this->assertEquals('500,0',                    $actual[3][4]);
        $this->assertEquals('Base deposit',             $actual[3][5]);
    }




}
