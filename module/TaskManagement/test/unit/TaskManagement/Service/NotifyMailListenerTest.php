<?php
namespace TaskManagement\Service;

use AcMailer\Result\MailResult;
use AcMailer\Service\MailServiceInterface;
use Application\Entity\User;
use Application\Service\FrontendRouter;
use Application\Service\UserService;
use People\Entity\Organization;
use TaskManagement\Entity\Task;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\TaskMember;
use Zend\Mail\Message;
use People\Service\OrganizationService;
use Rhumsaa\Uuid\Uuid;

class NotifyMailListenerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var NotificationService
     */
    protected $service;

    /**
     * @var Task
     */
    protected $task;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var User
     */
    protected $member;
    
    protected $organization;
    
    protected $stream;
    
    protected $orgService;

    protected $feRouter;

    protected function setUp()
    {
        $this->organization = new Organization('1');
        $this->organization->setName('Organization Name');
        
        $this->stream = new Stream('1', $this->organization);
        $this->stream->setSubject("Stream subject");

        $this->owner = User::createUser(Uuid::uuid4(), 'john.doe@foo.com', 'John', 'Doe');
        $this->owner->addMembership($this->organization);

        $this->member = User::createUser(Uuid::uuid4(), 'jane.doe@foo.com', 'Jane', 'Doe');
        $this->member->addMembership($this->organization);

        $this->task = new Task('1', $this->stream);
        $this->task->setSubject('Lorem Ipsum Sic Dolor Amit');
        $this->task->addMember($this->owner, TaskMember::ROLE_OWNER, $this->owner, new \DateTime());
        $this->task->addMember($this->member, TaskMember::ROLE_MEMBER, $this->member, new \DateTime());

        $this->feRouter = $this->getMockBuilder(FrontendRouter::class)->getMock();

        $mailService = $this->getMockBuilder(MailServiceInterface::class)->getMock();
        $mailService->expects($this->atLeastOnce())
            ->method('send')
            ->willReturn(new MailResult(true));
        $mailService->expects($this->atLeastOnce())
            ->method('getMessage')
            ->willReturn(new Message());

        $userService = $this->getMockBuilder(UserService::class)->getMock();
        $taskService = $this->getMockBuilder(TaskService::class)->getMock();


        $this->orgService = $this->getMockBuilder(OrganizationService::class)->getMock();

		$this->service = new NotifyMailListener(
		    $mailService,
            $userService,
            $taskService,
            $this->orgService,
            $this->feRouter
        );

		$this->service->setHost('http://example.com');
	}


    public function testSendEstimationAddedInfoMail()
    {
        $this->service->getMailService()
            ->expects($this->once())
            ->method('setTemplate')
            ->with('mail/estimation-added-info.phtml', [
                'task' => $this->task,
                'recipient' => $this->owner,
                'member' => $this->member,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);

        $this->service->sendEstimationAddedInfoMail($this->task, $this->member);
    }
    
    public function testSendSharesAssignedInfoMail()
    {
        $this->service->getMailService()
            ->expects($this->once())
            ->method('setTemplate')
            ->with('mail/shares-assigned-info.phtml', [
                'task' => $this->task,
                'recipient' => $this->owner,
                'member' => $this->member,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);
        $this->service->sendSharesAssignedInfoMail($this->task, $this->member);
    }
    
    public function testRemindAssignmentOfShares()
    {
        $this->service->getMailService()
            ->expects($this->at(1))
            ->method('setTemplate')
            ->with('mail/reminder-assignment-shares.phtml', [
                'task' => $this->task,
                'recipient'=> $this->owner,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);
        $this->service->getMailService()
            ->expects($this->at(4))
            ->method('setTemplate')
            ->with('mail/reminder-assignment-shares.phtml', [
                'task' => $this->task,
                'recipient'=> $this->member,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);
        $this->service->remindAssignmentOfShares($this->task);
    }

    public function testRemindEstimation()
    {
        $this->service->getMailService()
            ->expects($this->at(1))
            ->method('setTemplate')
            ->with('mail/reminder-add-estimation.phtml', [
                'task' => $this->task,
                'recipient'=> $this->owner,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);
        $this->service->getMailService()
            ->expects($this->at(4))
            ->method('setTemplate')
            ->with('mail/reminder-add-estimation.phtml', [
                'task' => $this->task,
                'recipient'=> $this->member,
                'host' => 'http://example.com',
                'router' => $this->feRouter
            ]);
        $this->service->remindEstimation($this->task);
    }

    public function testSendWorkItemIdeaCreatedMail()
    {
        $this->orgService->method('findOrganization')->with($this->task->getOrganizationId())->willReturn($this->organization);
        
        $m1 = new \People\Entity\OrganizationMembership($this->owner, $this->organization);
        $m2 = new \People\Entity\OrganizationMembership($this->member, $this->organization);
        $memberships = array($m1, $m2);

        $this->service->getMailService()
        ->expects($this->at(1))
        ->method('setTemplate')
        ->with('mail/work-item-idea-created.phtml', [
                'task' => $this->task,
                'member' =>$this->owner,
                'recipient'=> $this->owner,
                'organization'=> $this->organization,
                'stream'=> $this->stream,
            'host' => 'http://example.com',
            'router' => $this->feRouter
        ]);

        $this->service->getMailService()
        ->expects($this->at(4))
        ->method('setTemplate')
        ->with('mail/work-item-idea-created.phtml', [
                'task' => $this->task,
                'member' =>$this->owner,
                'recipient'=> $this->member,
                'organization'=> $this->organization,
                'stream'=> $this->stream,
            'host' => 'http://example.com',
            'router' => $this->feRouter
        ]);
        
        $this->service->sendWorkItemIdeaCreatedMail($this->task, $this->owner, $memberships);
    }
}
