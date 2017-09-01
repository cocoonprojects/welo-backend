<?php

namespace Application\Service;

use TaskManagement\CreditsAssigned;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;

class SendEventToProxyListener implements ListenerAggregateInterface
{
    protected $listeners = array();

    protected $eventProxyService;

    public function __construct(EventProxyService $eps)
    {
        $this->eventProxyService = $eps;
    }

    public function attach(EventManagerInterface $events)
    {
        $sm = $events->getSharedManager();

        $this->listeners[] = $sm->attach(Application::class, CreditsAssigned::class, [$this, 'sendEvent']);

    }

    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {

            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function sendEvent(Event $event)
    {
        $this->eventProxyService->send($event);
    }
}