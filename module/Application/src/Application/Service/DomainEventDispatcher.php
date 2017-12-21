<?php
namespace Application\Service;

use Prooph\EventStore\Stream\StreamEvent;
use Rhumsaa\Uuid\Uuid;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\EventManagerInterface;
use Prooph\EventStore\PersistenceEvent\PostCommitEvent;

class DomainEventDispatcher implements ListenerAggregateInterface
{
	protected $listeners = array();

	public function attach(EventManagerInterface $events) {
		$that = $this;
		$this->listeners[] = $events->getSharedManager()->attach('prooph_event_store', 'commit.post',
			function(PostCommitEvent $event) use ($that, $events) {
				foreach ($event->getRecordedEvents() as $streamEvent) {
					$eventName = $streamEvent->eventName();

                    if (strpos($eventName->toString(), '\\Event\\') !== false) {
                        $stuff = $this->translateToAggregateChangedEvent($streamEvent);
                        $events->trigger($stuff);
                    } else {
                        $events->trigger($eventName->toString(), $streamEvent, $streamEvent->payload());
                    }
				}
			}); // Execute business processes after read model update
	}
	
	public function detach(EventManagerInterface $events)
	{
		if ($events->getSharedManager()->detach('prooph_event_store', $this->listeners[0])) {
			unset($this->listeners[0]);
		}
	}


    protected function translateToAggregateChangedEvent(StreamEvent $streamEvent)
    {
        if (! class_exists($streamEvent->eventName()->toString())) {
            throw new \RuntimeException(
                sprintf(
                    'Event %s can not be constructed. EventName is no valid class name',
                    $streamEvent->eventName()->toString()
                )
            );
        }
        $eventClass = $streamEvent->eventName()->toString();
        $payload = $streamEvent->payload();
        $aggregateId = $payload['aggregate_id'];
        unset($payload['aggregate_id']);
        return $eventClass::reconstitute(
            $aggregateId,
            $payload,
            Uuid::fromString($streamEvent->eventId()->toString()),
            $streamEvent->occurredOn(),
            $streamEvent->version()
        );
    }
}