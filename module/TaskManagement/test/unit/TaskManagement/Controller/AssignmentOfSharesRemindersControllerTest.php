<?php
namespace TaskManagement\Controller;

use Application\Entity\User;
use People\Entity\Organization;
use People\Service\OrganizationService;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\Task;
use TaskManagement\Service\NotifyMailListener;
use TaskManagement\Service\TaskService;
use ZFX\Test\Controller\ControllerTest;

class AssignmentOfSharesRemindersControllerTest extends ControllerTest
{
    /**
     * @var User
     */
    private $systemUser;

    protected $org;
    protected $task;
    protected $owner;
    protected $member;
    protected $taskServiceStub;

    public function setupMore()
    {
        $this->systemUser = User::createUser(Uuid::uuid4(), null, null, null, null, User::ROLE_SYSTEM);
    }

    protected function setupController()
    {
        $this->org = new Organization('1');
        $this->task = new Task('1', new Stream('1', $this->org));
        $this->owner = User::createUser(Uuid::uuid4(), 'taskowner@orateam.com');
        $this->member = User::createUser(Uuid::uuid4(), 'taskmember@orateam.com');

        $this->task
            ->addMember($this->owner, Task::ROLE_OWNER, $this->owner, new \DateTime())
            ->addMember($this->member, Task::ROLE_MEMBER, $this->member, new \DateTime())
            ->setStatus(Task::STATUS_ACCEPTED);

        //Task Service Mock
        $this->taskServiceStub = $this->getMockBuilder(TaskService::class)->getMock();
        $this->taskServiceStub
             ->method('findAcceptedTasksBefore')
             ->willReturn([$this->task]);

        $this->orgServiceStub = $this->getMockBuilder(OrganizationService::class)->getMock();
        $this->orgServiceStub
             ->method('findOrganization')
             ->willReturn($this->org);

        $notifyMailListenerStub = $this->getMockBuilder(NotifyMailListener::class)->disableOriginalConstructor()->getMock();

        return new RemindersController(
            $notifyMailListenerStub,
            $this->taskServiceStub,
            $this->orgServiceStub
        );
    }

    protected function setupRouteMatch()
    {
        return ['controller' => 'reminders'];
    }

    public function testCreateAsAnonymous()
    {
        $this->setupAnonymous();
        $this->routeMatch->setParam('type', 'assignment-of-shares');
        $this->request->setMethod('post');
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateAsSystemUser()
    {
        $this->setupLoggedUser($this->systemUser);
        $this->routeMatch->setParam('type', 'assignment-of-shares');
        $this->request->setMethod('post');
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testCreateANonExistentReminder()
    {
        $this->setupLoggedUser($this->systemUser);
        $this->routeMatch->setParam('type', 'foo');
        $this->request->setMethod('post');
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCreateWithoutReminderType()
    {
        $this->setupLoggedUser($this->systemUser);
        $this->request->setMethod('post');
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        $this->assertEquals(404, $response->getStatusCode());
    }
}
