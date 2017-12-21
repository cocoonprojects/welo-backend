<?php

namespace Application\Service;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;

abstract class Projector implements ListenerAggregateInterface
{
    protected $listeners = array();

    abstract public function getRegisteredEvents();

    public function attach(EventManagerInterface $events)
    {
        $sm = $events->getSharedManager();

        foreach ($this->getRegisteredEvents() as $eventFullName) {

            $eventChunks = explode('\\', $eventFullName);
            $eventName = array_pop($eventChunks);

            $this->listeners[] = $sm->attach(
                Application::class,
                $eventFullName,
                [$this, 'apply' . $eventName]
            );
        }
    }

    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {

            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }
}