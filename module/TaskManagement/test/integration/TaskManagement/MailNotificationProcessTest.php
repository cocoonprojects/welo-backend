<?php
namespace TaskManagement;

use Guzzle\Http\Client;
use IntegrationTest\Bootstrap;
use People\Organization;
use TaskManagement\Controller\SharesController;
use Zend\Http\Request;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use ZFX\Test\Authentication\AdapterMock;
use ZFX\Test\Authentication\OAuth2AdapterMock;

class MailNotificationProcessTest extends \PHPUnit_Framework_TestCase
{

	protected $controller;
	protected $request;
	protected $response;
	protected $routeMatch;
	protected $event;

	protected $task;
	protected $owner;
	protected $member;
	protected $organization;

	private $mailcatcher;

	private $transactionManager;

	protected $intervalForCloseTasks;

	protected function setUp()
	{
		$serviceManager = Bootstrap::getServiceManager();

		//Clean EmailMessages
		$this->mailcatcher = new Client('http://127.0.0.1:1080');
		$this->cleanEmailMessages();

        $organizationService = $serviceManager->get('People\OrganizationService');
        $this->organization = $organizationService->getOrganization('00000000-0000-0000-1000-000000000000');

		$userService = $serviceManager->get('Application\UserService');
		$this->owner = $userService->findUser('60000000-0000-0000-0000-000000000000');
		$this->member = $userService->findUser('70000000-0000-0000-0000-000000000000');
		$this->member2 = $userService->findUser('80000000-0000-0000-0000-000000000000');

		$streamService = $serviceManager->get('TaskManagement\StreamService');
		$stream = $streamService->getStream('00000000-1000-0000-0000-000000000000');

		$taskService = $serviceManager->get('TaskManagement\TaskService');
		$this->controller = new SharesController($taskService);
		$this->request	= new Request();
		$this->routeMatch = new RouteMatch(array('controller' => 'shares'));
		$this->event	  = new MvcEvent();
		$router = $serviceManager->get('HttpRouter');

		$this->event->setRouter($router);
		$this->event->setRouteMatch($this->routeMatch);
		$this->controller->setEvent($this->event);
		$this->controller->setServiceLocator($serviceManager);

		$adapter = new AdapterMock();
		$adapter->setEmail($this->owner->getEmail());
		$authService = $serviceManager->get('Zend\Authentication\AuthenticationService');
		$authService->authenticate($adapter);

		$pluginManager = $serviceManager->get('ControllerPluginManager');
		$this->controller->setPluginManager($pluginManager);

		$this->intervalForCloseTasks = new \DateInterval('P10D');

		$this->transactionManager = $serviceManager->get('prooph.event_store');
		$this->transactionManager->beginTransaction();
		$task = Task::create($stream, 'Cras placerat libero non tempor', $this->owner);
		$task->addMember($this->owner, Task::ROLE_OWNER);
		$task->addMember($this->member, Task::ROLE_MEMBER);
		$task->addMember($this->member2, Task::ROLE_MEMBER);
		$task->open($this->owner);
		$task->execute($this->owner);
		$this->task = $taskService->addTask($task);
		$this->transactionManager->commit();
	}

	public function tearDown()
    {
        $this->cleanEmailMessages();
    }

    public function testEstimationAddedNotification()
    {
        $this->cleanEmailMessages();

		$this->transactionManager->beginTransaction();
		$this->task->addEstimation(1500, $this->owner);//Owner addEstimation (No-Mail)
		$this->task->addEstimation(3100, $this->member);//Member addEstimation (Mail)
		$this->transactionManager->commit();

		$emails = $this->getEmailMessages();

		$this->assertNotEmpty($emails);
		$this->assertCount(1, $emails);
		$this->assertContains($this->task->getSubject(), $emails[0]->subject);
		$this->assertEmailHtmlContains('estimation', $emails[0]);
		$this->assertNotEmpty($emails[0]->recipients);
		$this->assertEquals($emails[0]->recipients[0], '<mark.rogers@ora.local>');

		$expected = "http://welo.dev/index.html#/{$this->task->getOrganizationId()}/items/{$this->task->getId()}";
        $this->assertEmailHtmlContains($expected, $emails[0]);
	}

	public function testSharesAssignedNotification()
    {
		$this->transactionManager->beginTransaction();
		$this->task->addEstimation(1500, $this->owner);
		$this->task->addEstimation(3100, $this->member);
		$this->task->addEstimation(2300, $this->member2);
		$this->task->complete($this->owner);
		$this->task->accept($this->owner, $this->intervalForCloseTasks);
		$this->transactionManager->commit();
		$this->cleanEmailMessages();

		$this->transactionManager->beginTransaction();
		$this->task->assignShares([ $this->owner->getId() => 0.4, $this->member->getId() => 0.1, $this->member2->getId() => 0.5 ], $this->member);
		$this->transactionManager->commit();

		$email = $this->getLastEmailMessage();

		$this->assertNotNull($email);
		$this->assertContains($this->task->getSubject(), $email->subject);
		$this->assertEmailHtmlContains('shares', $email);
		$this->assertNotEmpty($email->recipients);
		$this->assertEquals($email->recipients[0], '<mark.rogers@ora.local>');
	}

	public function testTaskClosedNotification()
    {
		$this->transactionManager->beginTransaction();
		$this->task->addEstimation(7, $this->owner);
		$this->task->addEstimation(7, $this->member);
		$this->task->addEstimation(7, $this->member2);
		$this->task->complete($this->owner);
		$this->task->accept($this->owner, $this->intervalForCloseTasks);
		$this->transactionManager->commit();
		$this->cleanEmailMessages();

		$this->transactionManager->beginTransaction();
        $this->task->assignShares([ $this->owner->getId() => 0.4, $this->member->getId() => 0.3, $this->member2->getId() => 0.3 ], $this->member);
        $this->task->assignShares([ $this->owner->getId() => 0.1, $this->member->getId() => 0.3, $this->member2->getId() => 0.6 ], $this->member2);
        $this->task->skipShares($this->owner);
        $this->transactionManager->commit();

        $this->transactionManager->beginTransaction();
        $this->task->close($this->owner);
        $this->transactionManager->commit();

		$emails = $this->getEmailMessages();

		$email1 = $emails[2];
		$email2 = $emails[3];
		$email3 = $emails[4];

		$body1 = $this->getEmailBody($email1)->getBody(true);
		$body2 = $this->getEmailBody($email2)->getBody(true);
		$body3 = $this->getEmailBody($email3)->getBody(true);

        $emailData1 = $this->extractDataTableFromClosedEmailBody($body1);
        $emailData2 = $this->extractDataTableFromClosedEmailBody($body2);
        $emailData3 = $this->extractDataTableFromClosedEmailBody($body3);

		$this->assertEquals($this->task->getStatus(), Task::STATUS_CLOSED);
		$this->assertNotNull($email1);
		$this->assertContains($this->task->getSubject(), $email1->subject);
		$this->assertNotEmpty($email1->recipients);
		$this->assertEquals($email1->recipients[0], '<mark.rogers@ora.local>');

        $this->assertContains('<td>Mark Rogers</td><td>25</td><td>1.8</td><td>n/a</td>', $emailData1);
        $this->assertContains('<td>Phil Toledo</td><td>30</td><td>2.1</td><td>0</td>', $emailData1);
        $this->assertContains('<td>Bruce Wayne</td><td>45</td><td>3.2</td><td>15</td>', $emailData1);
        $this->assertContains('<td>Mark Rogers</td><td>25</td><td>1.8</td><td>n/a</td>', $emailData2);
        $this->assertContains('<td>Phil Toledo</td><td>30</td><td>2.1</td><td>0</td>', $emailData2);
        $this->assertContains('<td>Bruce Wayne</td><td>45</td><td>3.2</td><td>15</td>', $emailData2);
        $this->assertContains('<td>Mark Rogers</td><td>25</td><td>1.8</td><td>n/a</td>', $emailData3);
        $this->assertContains('<td>Phil Toledo</td><td>30</td><td>2.1</td><td>0</td>', $emailData3);
        $this->assertContains('<td>Bruce Wayne</td><td>45</td><td>3.2</td><td>15</td>', $emailData3);
	}

	public function testSecondTaskClosedNotification()
    {
		$this->transactionManager->beginTransaction();
		$this->task->addEstimation(567, $this->owner);
		$this->task->addEstimation(567, $this->member);
		$this->task->addEstimation(567, $this->member2);
		$this->task->complete($this->owner);
		$this->task->accept($this->owner, $this->intervalForCloseTasks);
		$this->transactionManager->commit();
		$this->cleanEmailMessages();

		$this->transactionManager->beginTransaction();
        $this->task->assignShares([ $this->owner->getId() => 0.16, $this->member->getId() => 0.35, $this->member2->getId() => 0.49 ], $this->member);
        $this->task->assignShares([ $this->owner->getId() => 0.45, $this->member->getId() => 0.12, $this->member2->getId() => 0.43 ], $this->member2);
        $this->task->skipShares($this->owner);
        $this->transactionManager->commit();

        $this->transactionManager->beginTransaction();
        $this->task->close($this->owner);
        $this->transactionManager->commit();

		$emails = $this->getEmailMessages();

		$email1 = $emails[2];
		$email2 = $emails[3];
		$email3 = $emails[4];

		$body1 = $this->getEmailBody($email1)->getBody(true);
		$body2 = $this->getEmailBody($email2)->getBody(true);
		$body3 = $this->getEmailBody($email3)->getBody(true);

        $emailData1 = $this->extractDataTableFromClosedEmailBody($body1);
        $emailData2 = $this->extractDataTableFromClosedEmailBody($body2);
        $emailData3 = $this->extractDataTableFromClosedEmailBody($body3);

		$this->assertEquals($this->task->getStatus(), Task::STATUS_CLOSED);
		$this->assertNotNull($email1);
		$this->assertContains($this->task->getSubject(), $email1->subject);
		$this->assertNotEmpty($email1->recipients);
		$this->assertEquals($email1->recipients[0], '<mark.rogers@ora.local>');

        $this->assertContains('<td>Mark Rogers</td><td>30.5</td><td>172.9</td><td>n/a</td>', $emailData1);
        $this->assertContains('<td>Phil Toledo</td><td>23.5</td><td>133.2</td><td>11.5</td>', $emailData1);
        $this->assertContains('<td>Bruce Wayne</td><td>46</td><td>260.8</td><td>-3</td>', $emailData1);
	}

    /**
     * @depends testTaskClosedNotification
     */
	public function testShiftOut()
    {
        $rootDir = __DIR__ . '/../../../../..';
        $this->cleanEmailMessages();

        shell_exec("php $rootDir/public/index.php shiftoutwarning --days=0");

        $emails = $this->getEmailMessages();

        $email = $emails[0];
        $body = (string)$this->getEmailBody($email)->getBody();

        $this->assertEquals("Your contribution in the O.R.A. Team open governance", $email->subject);
        $this->assertContains("not been fully active in the open governance of O.R.A. Team in the last 90 days.", $body);

        $email = $emails[1];
        $body = (string)$this->getEmailBody($email)->getBody();

        $this->assertEquals("Mark Rogers contribution in the O.R.A. Team open governance", $email->subject);
        $this->assertContains("Mark Rogers has not been fully active", $body);
    }

	public function testTaskAcceptedNotification()
    {
		$this->transactionManager->beginTransaction();
		$this->task->addEstimation(1500, $this->owner);
		$this->task->addEstimation(3100, $this->member);
        $this->task->addEstimation(2300, $this->member2);
		$this->task->complete($this->owner);
		$this->transactionManager->commit();
		$this->cleanEmailMessages();

		$this->transactionManager->beginTransaction();
		$this->task->accept($this->owner, $this->intervalForCloseTasks);
		$this->transactionManager->commit();

		$email = $this->getLastEmailMessage();

		$this->assertEquals($this->task->getStatus(), Task::STATUS_ACCEPTED);
		$this->assertNotNull($email);
		$this->assertContains($this->task->getSubject(), $email->subject);
		$this->assertNotEmpty($email->recipients);
		$this->assertEquals($email->recipients[0], '<mark.rogers@ora.local>');
	}

	protected function cleanEmailMessages()
	{
		$request = $this->mailcatcher->delete('/messages');

		return $request->send();
	}

	protected function getEmailMessages()
	{
		$request = $this->mailcatcher->get('/messages');
		$response = $request->send();
		$json = json_decode($response->getBody());

		return $json;
	}

	public function getLastEmailMessage()
	{
		$messages = $this->getEmailMessages();
		if (empty($messages)) {
			$this->fail("No messages received");
		}
		// messages are in descending order
		return reset($messages);
	}

	public function getEmailBody($email)
    {
        $request = $this->mailcatcher->get("/messages/{$email->id}.html");

        return $request->send();
    }

	public function assertEmailHtmlContains($needle, $email, $description = '')
	{
		$request = $this->mailcatcher->get("/messages/{$email->id}.html");
		$response = $request->send();

		$this->assertContains($needle, (string)$response->getBody(), $description);
	}

    protected function extractDataTableFromClosedEmailBody($body)
    {
        $body = preg_replace('/>[\s\r\n]+</', '><', $body);

        $start = strpos($body, '<tbody') + 7;
        $end = strpos($body, '</tbody>');
        $dataTable = (substr($body, $start, $end - $start));

        return $dataTable;
    }
}