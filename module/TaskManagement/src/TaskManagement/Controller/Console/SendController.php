<?php

namespace TaskManagement\Controller\Console;

use Application\Service\EventProxyService;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream\StreamName;
use Zend\Mvc\Controller\AbstractConsoleController;

class SendController extends AbstractConsoleController {

    private $eventStore;

    private $eventProxy;

	public function __construct(EventStore $es, EventProxyService $ep)
    {
	    $this->eventStore = $es;
	    $this->eventProxy = $ep;
    }

    public function sendAction()
	{
        $eventId = $this->params('eventId');

        if (!$eventId) {
            $this->write("run with 'public/index.php sendtoproxy <eventId>");

            exit(1);
        }

        $events = $this->eventStore
                       ->loadEventsByMetadataFrom(new StreamName('event_stream'), ['eventId' => $eventId]);

        if (empty($events)) {
            $this->write("no event found");

            exit(1);
        }

        $result = $this->eventProxy
                       ->send($events[0]);

        return $result->getMessage();
	}

	private function write($msg)
	{
		$now = (new \DateTime('now'))->format('Y-m-d H:s');

		echo "[$now] ", $msg, "\n";
	}

}
