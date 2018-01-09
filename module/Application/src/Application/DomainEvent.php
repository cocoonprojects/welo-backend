<?php

namespace Application;

use Prooph\EventSourcing\AggregateChanged;
use Rhumsaa\Uuid\Uuid;
use Zend\EventManager\EventInterface;

abstract class DomainEvent extends AggregateChanged implements EventInterface
{
    public static function reconstitute($aggregateId, array $payload, Uuid $uuid, \DateTime $occurredOn, $version)
    {
        $event = new static($aggregateId, $payload, $uuid, $occurredOn, $version);

        foreach ($payload as $property => $value)
        {
            //salto le proprietà non mappate, mi permette di mappare gli attributi dell'evento in modo incrementale
            if (!property_exists($event, $property)) {
                continue;
            }

            if ($event->$property != null) {
                continue; // salto le proprietà già popolate dal costruttore del padre
            }

            $event->$property = $value;
        }

        return $event;
    }

    public function getName()
    {
        return static::class;
    }

    public function getTarget()
    {
        // TODO: Implement getTarget() method.
    }

    public function getParams()
    {
        // TODO: Implement getParams() method.
    }

    public function getParam($name, $default = null)
    {
        // TODO: Implement getParam() method.
    }

    public function setName($name)
    {
        // TODO: Implement setName() method.
    }

    public function setTarget($target)
    {
        // TODO: Implement setTarget() method.
    }

    public function setParams($params)
    {
        // TODO: Implement setParams() method.
    }

    public function setParam($name, $value)
    {
        // TODO: Implement setParam() method.
    }

    public function stopPropagation($flag = true)
    {
        // TODO: Implement stopPropagation() method.
    }

    public function propagationIsStopped()
    {
        // TODO: Implement propagationIsStopped() method.
    }
}