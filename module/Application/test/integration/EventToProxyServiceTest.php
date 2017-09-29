<?php

namespace Application;

use Application\Service\EventProxyService;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Prooph\EventStore\Stream\EventId;
use Prooph\EventStore\Stream\EventName;
use Prooph\EventStore\Stream\StreamEvent;

class EventToProxyServiceTest extends \PHPUnit_Framework_TestCase
{	
    private $proxyService;

    public function setUp()
    {
        $client = $this->getMockBuilder(Client::class)
                        ->setMethods(['post', 'send'])
                        ->getMock();

        $request = $this->getMockBuilder(Request::class)
                        ->disableOriginalConstructor()
                        ->setMethods(['setBody'])
                        ->getMock();

        $client->expects($this->once())->method('post')->willReturn($request);
        $client->expects($this->once())->method('send');

        $this->proxyService = new EventProxyService($client);
    }

	public function testSendEvent()
    {
        $event = new StreamEvent(
            EventId::generate(),
            new EventName('test'),
            ['a' => '1', 'b' => 'banana', 'c' => 'batman'],
            2,
            new \DateTime(),
            ['aggregate_id' => '002211', 'aggregate_type']
        );

        $this->proxyService
             ->send($event);
	}
}
