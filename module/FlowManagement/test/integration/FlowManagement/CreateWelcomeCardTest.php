<?php
namespace TaskManagement;

use Application\Entity\User;
use IntegrationTest\BaseIntegrationTest;
use IntegrationTest\Bootstrap;
use People\Organization;
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
        $this->transactionManager->beginTransaction();
        try {
            $this->organization->addMember($this->user, Organization::ROLE_MEMBER);
            $this->transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $this->transactionManager->rollback();
            throw $e;
        }

		$result   = $this->controller->dispatch($this->request);
		$response = $this->controller->getResponse();

		$readModelCards = $this->flowService->findFlowCards($this->user, 0, 1000);
        $cardContent = $readModelCards[0]->getContent();

        $this->assertEquals(200, $response->getStatusCode());
		$this->assertCount(1, $readModelCards);
        $this->assertArrayHasKey('Welcome', $cardContent);
        $this->assertEquals($this->organization->getId(), $cardContent['Welcome']['orgId']);
        $this->assertNotEmpty($cardContent['Welcome']['text']);
        $this->assertEquals($this->organization->getParams()->get('flow_welcome_card_text'), $cardContent['Welcome']['text']);
    }

}
