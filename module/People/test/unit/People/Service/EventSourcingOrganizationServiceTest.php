<?php
namespace People\Service;

use Prooph\EventStoreTest\TestCase;
use Prooph\EventStore\Stream\Stream;
use Prooph\EventStore\Stream\StreamName;
use Application\Entity\User;
use Rhumsaa\Uuid\Uuid;
use Doctrine\ORM\EntityManager;

class EventSourcingOrganizationServiceTest extends TestCase
{
    private $organizationService;

    private $user;
    
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

        $this->organizationService = new EventSourcingOrganizationService($this->eventStore, $entityManager);
        $this->user = User::createUser(Uuid::uuid4());
    }
    
    public function testCreateOrganization()
    {
        $organization = $this->organizationService->createOrganization('Donec cursus vel nisi in', $this->user);
        
        $this->assertAttributeInstanceOf('Rhumsaa\Uuid\Uuid', 'id', $organization);
        $this->assertAttributeEquals('Donec cursus vel nisi in', 'name', $organization);
    }

    public function testCreateOrganizationWithoutName()
    {
        $organization = $this->organizationService->createOrganization(null, $this->user);
        
        $this->assertAttributeInstanceOf('Rhumsaa\Uuid\Uuid', 'id', $organization);
        $this->assertNull($organization->getName());
    }
}
