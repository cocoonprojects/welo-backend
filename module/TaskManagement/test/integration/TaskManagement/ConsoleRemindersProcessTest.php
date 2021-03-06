<?php

namespace TaskManagement;

use Rhumsaa\Uuid\Uuid;
use TaskManagement\Controller\Console\RemindersController;
use Guzzle\Http\Client;
use IntegrationTest\Bootstrap;
use Application\Entity\User;
use Application\Service\FrontendRouter;
use People\Entity\Organization;
use People\Service\OrganizationService;
use TaskManagement\Entity\Task as EntityTask;
use TaskManagement\Entity\Stream as EntityStream;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Entity\Vote;
use TaskManagement\Service\TaskService;
use Zend\Console\Request as ConsoleRequest;
use TaskManagement\Service\MailService;

class ConsoleRemindersProcessTest extends \PHPUnit_Framework_TestCase
{
    private $controller;
    private $owner;
    private $member;
    private $task;
    private $taskService;
    private $orgService;
    private $mailService;

    protected function setUp()
    {
        $serviceManager = Bootstrap::getServiceManager();
        $this->mailService = $serviceManager->get('AcMailer\Service\MailService');

        $this->mailcatcher = new Client('http://127.0.0.1:1080');

        $this->organization = new Organization('1');
        $this->organization->setName('Organization Name');

        $this->stream = new EntityStream('1', $this->organization);
        $this->stream->setSubject("Stream subject");

        $this->owner = User::createUser(Uuid::uuid4(), 'john.doe@foo.com', 'John', 'Doe');
        $this->owner->addMembership($this->organization);

        $this->member = User::createUser(Uuid::uuid4(), 'jane.doe@foo.com', 'Jane', 'Doe');
        $this->member->addMembership($this->organization);

        $this->task = new EntityTask('1', $this->stream);
        $this->task->setSubject('Lorem Ipsum Sic Dolor Amit');
        $this->task->addMember($this->owner, TaskMember::ROLE_OWNER, $this->owner, new \DateTime());
        $this->task->addMember($this->member, TaskMember::ROLE_MEMBER, $this->member, new \DateTime());

        $vote = new Vote(new \DateTime('today'));
        $vote->setValue(1);
        $this->task->addApproval($vote, $this->owner, new \DateTime('today'), 'Voto a favore');

        $this->taskService = $this->getMockBuilder(TaskService::class)->getMock();
        $this->orgService = $this->getMockBuilder(OrganizationService::class)->getMock();
        $this->feRouter = $this->getMockBuilder(FrontendRouter::class)->getMock();

        $this->controller = new RemindersController(
            $this->taskService,
            $this->mailService,
            $this->orgService,
            $this->feRouter
        );

        $this->request = new ConsoleRequest();

        $this->cleanEmailMessages();
    }


    protected function cleanEmailMessages()
    {
        $request = $this->mailcatcher->delete('/messages');
        $response = $request->send();
    }

    protected function getEmailMessages()
    {
        $request = $this->mailcatcher->get('/messages');
        $response = $request->send();
        $json = json_decode($response->getBody());
        return $json;
    }

    public function assertEmailHtmlContains($needle, $email, $description = '')
    {
        $request = $this->mailcatcher->get("/messages/{$email->id}.html");
        $response = $request->send();
        $this->assertContains($needle, (string)$response->getBody(), $description);
    }

    public function testSendNotificationToUserWhoDidntVote()
    {
        $this->taskService
            ->method('findIdeasCreatedBetween')
            ->willReturn([$this->task]);

        $this->orgService
            ->method('findOrganizations')
            ->willReturn([$this->organization]);

        ob_start();

        $this->controller->sendAction();

        $result = ob_get_clean();

        $emails = $this->getEmailMessages();

        $this->assertNotEmpty($emails);
        $this->assertCount(1, $emails);
        $this->assertContains($this->task->getSubject(), $emails[0]->subject);
        $this->assertEmailHtmlContains('approval', $emails[0]);
        $this->assertNotEmpty($emails[0]->recipients);
        $this->assertEquals($emails[0]->recipients[0], '<jane.doe@foo.com>');
    }
}
