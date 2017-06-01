<?php
namespace TaskManagement\Service;

use Prooph\EventStoreTest\TestCase;
use Prooph\EventStore\Stream\Stream as ProophStream;
use Prooph\EventStore\Stream\StreamName;
use Rhumsaa\Uuid\Uuid;
use Application\Entity\User;
use People\Organization;
use Doctrine\ORM\EntityManager;

class EventSourcingStreamServiceTest extends TestCase
{
    private $streamService;

    private $user;
    
    protected function setUp()
    {
        parent::setUp();

        $entityManager = $this->getMockBuilder(EntityManager::class)
            ->setMethods(array('getRepository', 'getClassMetadata', 'persist', 'flush'))
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventStore->beginTransaction();
        $this->eventStore->create(new ProophStream(new StreamName('event_stream'), array()));
        $this->eventStore->commit();

        $this->streamService = new EventSourcingStreamService($this->eventStore, $entityManager);

        $this->user = User::createUser(Uuid::uuid4());
    }
    
    public function testCreate()
    {
        $organization = Organization::create('Quisque quis tortor ligula. Duis', $this->user);
        $stream = $this->streamService->createStream($organization, 'Mauris vel lectus pellentesque, cursus', $this->user);

        $this->assertNotNull($stream->getId());
        $this->assertEquals('Mauris vel lectus pellentesque, cursus', $stream->getSubject());
        $this->assertEquals($organization->getId(), $stream->getOrganizationId());
    }
}
