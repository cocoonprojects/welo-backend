<?php
namespace TaskManagement;

use Application\Entity\User;
use IntegrationTest\BaseIntegrationTest;
use IntegrationTest\Bootstrap;
use People\Organization;
use PHPUnit_Framework_TestCase;
use TaskManagement\Controller\SharesController;
use Test\TestFixturesHelper;
use Test\ZFHttpClient;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack as HttpRouter;
use Zend\Mvc\Router\RouteMatch;
use Zend\Uri\Http;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;
use Behat\Testwork\Tester\Setup\Teardown;
use ZFX\Test\WebTestCase;

class CreateWelcomeCardTest extends WebTestCase
{	
	protected $task;
    protected $admin;
	protected $member;
	protected $organization;

	/**
	 * @var \DateInterval
	 */
	protected $intervalForCloseTasks;
	protected $client;

	public function setUp()
	{
        parent::setUp();

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));

        $this->admin = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $this->member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $orgData = $this->fixtures->createOrganization('my org', $this->admin, []);
        $this->organization = $orgData['org'];
	}

	public function testWelcomeFlowcard() {
	    $orgId = $this->organization->getId();
	    $memberId = $this->member->getId();

        $response = $this->client
            ->put("/{$orgId}/people/members/{$memberId}", [
                'memberId' => $this->member->getId(),
                'orgId' => $orgId,
                'role' => 'member'
            ]);


        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $this->countWelcomeFlowCardForOrg($this->organization->getId()));
    }


    protected function countWelcomeFlowCardForOrg($orgId)
    {
        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0&org='.$orgId);

        $flowCards = json_decode($response->getContent(), true);
        $count = 0;

        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'Welcome') {
                $count++;
            }
        }

        return $count;
    }
}
