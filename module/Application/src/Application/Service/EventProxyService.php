<?php

namespace Application\Service;

use Guzzle\Http\Client;
use Prooph\EventStore\Stream\StreamEvent;

class EventProxyService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function send(StreamEvent $event)
    {
        $data = [
            'eventName' => $event->eventName()->toString(),
            'payload' => $event->payload(),
            'occuredOn' => $event->occurredOn(),
            'metadata' => $event->metadata(),
            'version' => $event->version(),
            'aggregateId' => $event->metadata()['aggregate_id']
        ];

        $data = json_encode($data);

        $request = $this->client->post('/eventproxy', ['Content-Type' => 'application/json']);
        $request->setBody($data);

        return $this->client->send($request);
    }
}