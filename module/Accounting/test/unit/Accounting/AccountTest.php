<?php
namespace Accounting;

use Application\Entity\User;
use People\Organization;
use Rhumsaa\Uuid\Uuid;

class AccountTest extends \PHPUnit_Framework_TestCase
{
    private $holder;

    private $organization;
    
    protected function setUp()
    {
        $this->holder = User::createUser(Uuid::uuid4(), null, 'John', 'Doe');
        $this->organization = Organization::create('Lorem Ipsum', User::createUser(Uuid::uuid4()));
    }
    
    public function testCreate()
    {
        $account = Account::create($this->organization, $this->holder);
        $this->assertNotEmpty($account->getId());
        $this->assertEquals(0, $account->getBalance()->getValue());
        $this->assertArrayHasKey($this->holder->getId(), $account->getHolders());
        $this->assertEquals($this->holder->getFirstname() . ' ' . $this->holder->getLastname(), $account->getHolders()[$this->holder->getId()]);
    }
    
    public function testAddHolder()
    {
        $holder = User::createUser(Uuid::uuid4(), null, 'Jane', 'Smith');
        $account = Account::create($this->organization, $this->holder);
        $account->addHolder($holder, $this->holder);
        $this->assertArrayHasKey($holder->getId(), $account->getHolders());
        $this->assertEquals($holder->getFirstname() . ' ' . $holder->getLastname(), $account->getHolders()[$holder->getId()]);
    }
    
    public function testDeposit()
    {
        $account = Account::create($this->organization, $this->holder);
        $account->deposit(100, $this->holder, null);
        $this->assertEquals(100, $account->getBalance()->getValue());
    }
    
    /**
     * @expectedException Accounting\IllegalAmountException
     */
    public function testDepositWithNegativeAmount()
    {
        $account = Account::create($this->organization, $this->holder);
        $account->deposit(-100, $this->holder, null);
        $this->assertEquals(0, $account->getBalance()->getValue());
    }
    
    public function testTransferIn()
    {
        $payee = Account::create($this->organization, $this->holder);
        
        $user = User::createUser(Uuid::uuid4());
        $payer = Account::create($this->organization, $user);
        
        $payee->transferIn(100, $payer, 'Bonifico', $user);
        $this->assertEquals(100, $payee->getBalance()->getValue());
    }
    
    /**
     * @expectedException Accounting\IllegalAmountException
     */
    public function testTransferInWithNegativeAmount()
    {
        $payee = Account::create($this->organization, $this->holder);
        
        $user = User::createUser(Uuid::uuid4());
        $payer = Account::create($this->organization, $user);
        
        $payee->transferIn(-100, $payer, 'Bonifico', $user);
        $this->assertEquals(0, $payee->getBalance()->getValue());
    }
    
    public function testTransferOut()
    {
        $payer = Account::create($this->organization, $this->holder);
        
        $user = User::createUser(Uuid::uuid4());
        $payee = Account::create($this->organization, $user);
        
        $payer->transferOut(-100, $payee, 'Bonifico', $user);
        $this->assertEquals(-100, $payer->getBalance()->getValue());
    }

    /**
     * @expectedException Accounting\IllegalAmountException
     */
    public function testTransferOuWithNegativeAmount()
    {
        $payer = Account::create($this->organization, $this->holder);
        
        $user = User::createUser(Uuid::uuid4());
        $payee = Account::create($this->organization, $user);
        
        $payer->transferOut(100, $payee, 'Bonifico', $user);
        $this->assertEquals(0, $payer->getBalance()->getValue());
    }
}
