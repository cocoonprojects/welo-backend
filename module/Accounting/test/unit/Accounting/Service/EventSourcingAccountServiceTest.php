<?php
namespace Accounting\Service;

use Prooph\EventStoreTest\TestCase;
use Prooph\EventStore\Stream\Stream;
use Prooph\EventStore\Stream\StreamName;
use Rhumsaa\Uuid\Uuid;
use Application\Entity\User;
use People\Organization;
use Doctrine\ORM\EntityManager;

class EventSourcingAccountServiceTest extends TestCase
{
    protected $accountService;
    
    protected $user;

    protected $organization;
        
    protected function setUp()
    {
        parent::setUp();
        $entityManager = $this->getMockBuilder(EntityManager::class)
            ->setMethods(array('getRepository', 'getClassMetadata', 'persist', 'flush'))
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventStore->beginTransaction();
        $this->eventStore->create(new Stream(new StreamName('event_stream'), array()));
        $this->eventStore->commit();

        $this->accountService = new EventSourcingAccountService($this->eventStore, $entityManager);

        $this->user = User::createUser(Uuid::uuid4());
        $this->organization = Organization::create('Test', $this->user);
    }

    public function testCreatePersonalAccount()
    {
        $account = $this->accountService->createPersonalAccount($this->user, $this->organization);
        $this->assertInstanceOf('Accounting\Account', $account);
        $this->assertAttributeInstanceOf('Rhumsaa\Uuid\Uuid', 'id', $account);
    }

    public function testCreateOrganizationAccount()
    {
        $holder = $this->user;
        $account = $this->accountService->createOrganizationAccount($this->organization, $holder);
        $this->assertInstanceOf('Accounting\OrganizationAccount', $account);
        $this->assertAttributeInstanceOf('Rhumsaa\Uuid\Uuid', 'id', $account);
        $a = $this->accountService->getAccount($account->getId());
        $this->assertInstanceOf('Accounting\OrganizationAccount', $a);
    }
    
    public function testDeposit()
    {
        $holder = $this->user;
        $account = $this->accountService->createOrganizationAccount($this->organization, $holder);
        $balance = $account->getBalance()->getValue();
        $this->eventStore->beginTransaction();
        $account->deposit(150, $holder, "My first deposit");
        $this->eventStore->commit();
        $this->assertEquals($balance + 150, $account->getBalance()->getValue());
    }
}
