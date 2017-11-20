<?php
namespace TaskManagement;

use FlowManagement\Entity\ItemDeletedCard;
use TaskManagement\Entity\Vote;
use Test\TestFixturesHelper;
use Test\Mailbox;
use Test\ZFHttpClient;
use IntegrationTest\Bootstrap;

class CreditsNotificationsTest extends \PHPUnit_Framework_TestCase
{
    protected $client;
    protected $fixtures;
    protected $serviceManager;
    protected $flowService;
    protected $userService;

    public function setUp()
    {
        $config = getenv('APP_ROOT_DIR') . '/config/application.test.config.php';
        $this->serviceManager = Bootstrap::getServiceManager();

        $this->client = ZFHttpClient::create($config);
        $this->client->enableErrorTrace();

        $this->flowService = $this->serviceManager->get('FlowManagement\FlowService');
        $this->accountService = $this->serviceManager->get('Accounting\CreditsAccountsService');

        $this->fixtures = new TestFixturesHelper($this->client->getServiceManager());

        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
    }

    public function testCreditsTransferNotifications()
    {
        $owner = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org', $owner, [$member]);
        $task = $this->fixtures->createOngoingTask('Lorem Ipsum Sic Dolor Amit', $res['stream'], $owner, [$member]);

        $mailbox = Mailbox::create();
        $mailbox->clean();

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/deposits",
                [
                    'amount' => 500,
                    'description' => "Base deposit"
                ]
            );

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/outgoing-transfers",
                [
                    'amount' => 150,
                    'description' => "Beccate sti du crediti",
                    'payee' => "phil.toledo@ora.local"
                ]
            );

        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/accounting/accounts/{$res['org']->getAccountId()}/outgoing-transfers",
                [
                    'amount' => 123,
                    'description' => "E anche sti altri due",
                    'payee' => "phil.toledo@ora.local"
                ]
            );

        $this->assertEquals(201, $response->getStatusCode());

        // users get notified via mail
        $messages = $mailbox->getMessages();
        $this->assertEquals(2, $this->countCreditsAddedEmails($member->getEmail(), $messages));


        $this->client->setJWTToken($this->fixtures->getJWTToken('phil.toledo@ora.local'));

        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0');
        $flowCards = json_decode($response->getContent(), true);

        $this->assertEquals(2, $this->countCreditsAddedFlowCard($flowCards));


        $userAccount = $this->accountService->findPersonalAccount($member, $res['org']);
        $response = $this->client
            ->get(
                "/{$res['org']->getId()}/accounting/accounts/{$userAccount->getId()}"
            );
        $balance = json_decode($response->getContent());
        $this->assertEquals(273, $balance->balance);
    }


    public function testCreditsSharesNotifications()
    {
        $owner = $this->fixtures->findUserByEmail('bruce.wayne@ora.local');
        $member = $this->fixtures->findUserByEmail('phil.toledo@ora.local');

        $res = $this->fixtures->createOrganization('my org 2', $owner, [], [$member]);
        $task = $this->fixtures->createAcceptedTask('Credits Share Notifications item', $res['stream'], $owner, [$member]);

        $mailbox = Mailbox::create();
        $mailbox->clean();


        $transactionManager = $this->serviceManager->get('prooph.event_store');
        $transactionManager->beginTransaction();
        try {
            $task->assignShares([
                $owner->getId() => 0.40,
                $member->getId() => 0.60
            ], $owner);
            $task->assignShares([
                $owner->getId() => 0.50,
                $member->getId() => 0.50
            ], $member);
            $transactionManager->commit();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $transactionManager->rollback();
            throw $e;
        }


        $response = $this->client
            ->post(
                "/{$res['org']->getId()}/task-management/tasks/{$task->getId()}/transitions",
                [ "action" => "close" ]
            );
        $this->assertEquals('200', $response->getStatusCode());


        $this->client->setJWTToken($this->fixtures->getJWTToken('bruce.wayne@ora.local'));
        $response = $this->client
            ->get("/{$res['org']->getId()}/task-management/tasks/{$task->getId()}");
        $this->assertEquals('200', $response->getStatusCode());


        //users get notified via flowcard
        $response = $this->client
            ->get('/flow-management/cards?limit=10&offset=0');
        $flowCards = json_decode($response->getContent(), true);
        $this->assertEquals(1, $this->countCreditsAddedFlowCard($flowCards));

        $messages = $mailbox->getMessages();
        $this->assertEquals(1, $this->countCreditsAddedEmails($member->getEmail(), $messages));




    }


    protected function countCreditsAddedFlowCard($flowCards) {
        $count = 0;
        foreach ($flowCards['_embedded']['ora:flowcard'] as $idx => $flowCard) {
            if ($flowCard['type'] == 'CreditsAdded') {
                $count++;
            }
        }
        return $count;
    }


    protected function countCreditsAddedEmails($account ,$messages) {
        $count = 0;
        foreach ($messages as $idx => $message) {
            if (
                $message->recipients[0] == '<'.$account.'>' &&
                strpos($message->subject, 'credits transferred in your account from the') !== false
            ) {
                $count++;
            }
        }
        return $count;
    }


}
