<?php
namespace TaskManagement;

use Application\Entity\User;
use Application\Service\UserService;
use People\Entity\OrganizationMembership;
use PHPUnit_Framework_TestCase;
use TaskManagement\Controller\SharesController;
use TaskManagement\Entity\Vote;
use Test\TestFixturesHelper;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;
use Behat\Testwork\Tester\Setup\Teardown;
use People\Organization;
use TaskManagement\Stream;

class TaskApprovalTaskProcessTest extends \BaseTaskProcessTest
{
    protected $task;
    protected $author;
    protected $admin;
    protected $organization;

    /**
     * @var \DateInterval
     */
    protected $intervalForCloseTasks;

    protected function setUp()
    {
        parent::setupController('TaskManagement\Controller\Approvals', 'invoke');

        $this->admin = $this->userService->subscribeUser(
            ['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => TestFixturesHelper::generateRandomEmail()],
            User::ROLE_ADMIN
        );

        $this->author = $this->userService->subscribeUser(
            ['given_name' => 'John', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail()],
            User::ROLE_USER
        );

        $this->organization = $this->organizationService->createOrganization('approval', $this->admin);
        $this->organization->addMember($this->author, Organization::ROLE_MEMBER);

        $stream = $this->streamService->createStream($this->organization, 'stream', $this->admin);

        $this->author->addMembership($this->organization, OrganizationMembership::ROLE_MEMBER);

        $this->organization = $this->organizationService->getOrganization($this->organization->getId());

        $this->author = $this->userService->findUserByEmail($this->author->getEmail());
        $this->userService->refreshEntity($this->author);


        $adapter = new AdapterMock();
        $adapter->setEmail($this->author->getEmail());
        $this->authService = $this->serviceManager->get('Zend\Authentication\AuthenticationService');
        $this->authService->authenticate($adapter);

        $transactionManager = $this->serviceManager->get('prooph.event_store');

        $transactionManager->beginTransaction();
        try {
            $task = Task::create($stream, 'Cras placerat libero non tempor', $this->author);

            $this->task = $this->taskService->addTask($task);
            $this->task->addApproval(Vote::VOTE_ABSTAIN, $this->admin, 'Voto a favore');
            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e);
            $transactionManager->rollback();
            throw $e;
        }

        $taskReadModel = $this->taskService->findTask($task->getId());
        $this->taskService->refreshEntity($taskReadModel);
    }

    public function testTaskShouldBeApproved()
    {
        $this->routeMatch->setParam('id', $this->task->getId());

        $this->request->setMethod('post');
        $params = $this->request->getPost();
        $params->set('value', 1);
        $params->set('description', 'cool');
        $result   = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();

        $readModelTask = $this->taskService->findTask($this->task->getId());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(TaskInterface::STATUS_OPEN, $readModelTask->getStatus());
    }
}
