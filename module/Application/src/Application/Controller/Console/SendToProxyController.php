<?php

namespace Application\Controller\Console;

use Prooph\EventStore\EventStore;
use Zend\Mvc\Controller\AbstractConsoleController;
use Zend\Console\Request as ConsoleRequest;

class SendToProxyController extends AbstractConsoleController {

	protected $eventStore;

	public function __construct(EventStore $eventStore) {
        $this->eventStore = $eventStore;
    }

	public function sendAction()
    {
        $request = $this->getRequest();

        if (!$request instanceof ConsoleRequest) {
            $this->write("use only from a console!");

            exit(1);
        }

        $eventId = $this->params('eventId');

        if (!$eventId) {
            $this->write("run with 'public/index.php sendtoproxy <eventId>");

            exit(1);
        }

    }

}
