<?php
namespace TaskManagement;

use Application\Entity\User;
use IntegrationTest\BaseIntegrationTest;
use IntegrationTest\Bootstrap;
use PHPUnit_Framework_TestCase;
use TaskManagement\Controller\SharesController;
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

class CreateWelcomeCardTest extends BaseIntegrationTest
{	
	protected $task;
	protected $owner;
	protected $member;
	protected $organization;

	/**
	 * @var \DateInterval
	 */
	protected $intervalForCloseTasks;

	protected function setUp()
	{
        $this->request = new Request();

        $this->admin = $this->createUser(['given_name' => 'Admin', 'family_name' => 'Uber', 'email' => TestFixturesHelper::generateRandomEmail()], User::ROLE_ADMIN);
        $this->user = $this->createUser([ 'given_name' => 'John', 'family_name' => 'Doe', 'email' => TestFixturesHelper::generateRandomEmail() ], User::ROLE_USER);

        $this->organization = $this->createOrganization(TestFixturesHelper::generateRandomName(), $this->admin);

        $this->setupController('FlowManagement\Controller\Cards', 'list');
        $this->setupAuthenticatedUser($this->user->getEmail());
	}

	public function testWelcomeFlowcard() {
		$result   = $this->controller->dispatch($this->request);
		$response = $this->controller->getResponse();
var_dump($response->getStatusCode());
var_dump($response->getContent());

		$readModelCards = $this->flowService->findFlowCards($this->user, 0, 1000);
var_dump($readModelCards);
		$this->assertCount(1, $readModelCards);
/*
		$this->assertEquals(201, $response->getStatusCode());
		$this->assertEquals(Task::STATUS_ACCEPTED, $this->task->getStatus());
		$this->assertEquals(Task::STATUS_ACCEPTED, $readModelTask->getStatus());
		$this->assertEquals(true, $this->task->isSharesAssignmentCompleted());
*/
	}

}
