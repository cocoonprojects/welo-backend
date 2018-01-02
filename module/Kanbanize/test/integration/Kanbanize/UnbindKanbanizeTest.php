<?php

namespace Kanbanize;

use ZFX\Test\WebTestCase;
use IntegrationTest\Bootstrap;

class UnbindKanbanizeTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->serviceManager = Bootstrap::getServiceManager();

        $this->admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $this->member1 = $this->fixtures->findUserByEmail('phil.toledo@ora.local');
        $this->member2 = $this->fixtures->findUserByEmail('paul.smith@ora.local');

        $res = $this->fixtures->createOrganization('my kanbanize org', $this->admin, [], [$this->member1, $this->member2]);

        $this->org = $res['org'];
        $this->stream = $res['stream'];

        $transactionManager = $this->serviceManager->get('prooph.event_store');
        $transactionManager->beginTransaction();

        try {
            $this->org->setSettings(\People\Organization::KANBANIZE_SETTINGS, [
                "apiKey" => "foo_api_key_foo",
                "accountSubdomain" => "bar_subdomain_bar"
            ], $this->admin);
            $this->org->setlanes([12 => 'foolane', 13 => 'barlane'], $this->admin);

            $this->stream->bindToKanbanizeBoard(123, $this->admin);

            $transactionManager->commit();
        } catch (\Exception $e) {
            $transactionManager->rollback();
        }
    }

    public function testUnbindKanbanize()
    {
        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $response = $this->client
            ->get("/{$this->org->getId()}/kanbanize/settings");
        $this->assertNotEquals('{}', $response->getContent());


        $response = $this->client
            ->delete("/{$this->org->getId()}/kanbanize/settings/boards");
        $this->assertEquals('200', $response->getStatusCode());


        $response = $this->client
            ->get("/{$this->org->getId()}/kanbanize/settings");
        $this->assertEquals('{}', $response->getContent());

        $stream = $this->serviceManager->get('TaskManagement\StreamService')->getStream($this->stream->getId());
        $this->assertNull($stream->getBoardId());

        $response = $this->client
            ->post("/{$this->org->getId()}/task-management/tasks", [
                "subject" => "My First Task Without Kanbanize",
                "description" => "Lorem ipsum dolor sit amet, et bastam",
                "streamID" => $this->stream->getId()
            ]);
        $this->assertEquals('201', $response->getStatusCode());
        $res = json_decode($response->getContent(), true);

        $this->assertEquals($this->org->getId(), $res['organization']['id']);
        $this->assertEquals('My First Task Without Kanbanize', $res['subject']);
    }


    public function testCannotUnbindKanbanizeIfNotAdmin()
    {
        $this->client->setJWTToken($this->fixtures->getJWTToken('phil.toledo@ora.local'));

        $response = $this->client
            ->delete("/{$this->org->getId()}/kanbanize/settings/boards");
        $this->assertEquals('403', $response->getStatusCode());

    }
}
