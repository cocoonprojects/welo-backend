<?php
namespace Accounting;

use Application\Entity\User;
use People\Organization;
use Rhumsaa\Uuid\Uuid;

class OrganizationAccountTest extends \PHPUnit_Framework_TestCase
{
    private $holder;
    
    private $organization;
    
    protected function setUp()
    {
        $this->holder = User::createUser(Uuid::uuid4(), null, 'John', 'Doe');
        $this->organization = Organization::create('Lorem Ipsum', $this->holder);
    }
    
    public function testCreate()
    {
        $account = OrganizationAccount::create($this->organization, $this->holder);
        $this->assertNotEmpty($account->getId());
        $this->assertEquals(0, $account->getBalance()->getValue());
        $this->assertArrayHasKey($this->holder->getId(), $account->getHolders());
        $this->assertEquals($this->holder->getFirstname() . ' ' . $this->holder->getLastname(), $account->getHolders()[$this->holder->getId()]);
        $this->assertEquals($this->organization->getId(), $account->getOrganizationId());
    }
}
