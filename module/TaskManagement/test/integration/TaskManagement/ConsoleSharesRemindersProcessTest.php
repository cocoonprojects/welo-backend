<?php

namespace TaskManagement;

use Application\Service\FrontendRouter;
use TaskManagement\Controller\Console\SharesRemindersController;
use IntegrationTest\Bootstrap;
use Application\Entity\User;
use People\Entity\Organization;
use People\Service\OrganizationService;
use TaskManagement\Entity\Task as EntityTask;
use TaskManagement\Entity\Stream as EntityStream;
use TaskManagement\Entity\TaskMember;
use TaskManagement\Service\TaskService;
use Zend\Console\Request as ConsoleRequest;
use TaskManagement\Service\MailService;
use Test\Mailbox;

class ConsoleSharesRemindersProcessTest extends \PHPUnit_Framework_TestCase
{

	private $controller;
	private $owner;
	private $member;
	private $task;
	private $taskService;
	private $orgService;
	private $mailbox;
	private $feRouter;
	private $mailService;

	protected function setUp()
	{
        $serviceManager = Bootstrap::getServiceManager();

        $this->organization = new Organization('1');
        $this->organization->setName('Organization Name');

        $this->stream = new EntityStream('1', $this->organization);
        $this->stream->setSubject("Stream subject");

        $this->owner = User::create();
        $this->owner->setFirstname('John');
        $this->owner->setLastname('Doe');
        $this->owner->setEmail('john.doe@foo.com');
        $this->owner->addMembership($this->organization);

        $this->member = User::create();
        $this->member->setFirstname('Jane');
        $this->member->setLastname('Doe');
        $this->member->setEmail('jane.doe@foo.com');
        $this->member->addMembership($this->organization);

        $this->task = new EntityTask('1', $this->stream);
        $this->task->setSubject('Lorem Ipsum Sic Dolor Amit');
        $this->task->addMember($this->owner, TaskMember::ROLE_OWNER, $this->owner, new \DateTime());
        $this->task->addMember($this->member, TaskMember::ROLE_MEMBER, $this->member, new \DateTime());

        $this->task->setStatus(Task::STATUS_COMPLETED);

        $members = $this->task->getMembers();
        $ownerMember = array_shift($members);
        $memberMember = array_shift($members);
        $ownerMember->assignShare($ownerMember, 100, new \DateTime('today'));
        $ownerMember->assignShare($memberMember, 80, new \DateTime('today'));

        $this->task->updateMembersShare(new \DateTime('today'));

        $this->taskService = $this->getMockBuilder(TaskService::class)->getMock();
        $this->orgService = $this->getMockBuilder(OrganizationService::class)->getMock();
        $this->mailService = $serviceManager->get('AcMailer\Service\MailService');
        $this->feRouter = $this->getMockBuilder(FrontendRouter::class)->getMock();

		$this->controller = new SharesRemindersController(
			$this->taskService,
			$this->mailService,
			$this->orgService,
            $this->feRouter
		);

        $this->request = new ConsoleRequest();

        $this->mailbox = Mailbox::create();
    }


	public function testSendNotificationToUserWhoDidntAssignShares()
	{
        $this->mailbox->clean();

		$this->taskService
			->method('findAcceptedTasksBetween')
			->willReturn([$this->task]);

		$this->orgService
			->method('findOrganizations')
			->willReturn([$this->organization]);

		ob_start();

		$this->controller->sendAction();

		$result = ob_get_clean();

		$emails = $this->mailbox->getMessages();

		$this->assertNotEmpty($emails);
		$this->assertEquals(1, count($emails));
		$this->assertContains($this->task->getSubject(), $emails[0]->subject);
		$this->assertContains('Assign shares', $emails[0]->subject);
		$this->assertNotEmpty($emails[0]->recipients);
		$this->assertEquals($emails[0]->recipients[0], '<jane.doe@foo.com>');
	}
}
