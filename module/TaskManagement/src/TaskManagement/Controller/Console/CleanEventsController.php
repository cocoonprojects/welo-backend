<?php

namespace TaskManagement\Controller\Console;

use Application\Entity\EventStream;
use Doctrine\ORM\EntityManager;
use Prooph\EventStore\EventStore;
use Zend\Mvc\Controller\AbstractConsoleController;
use Zend\Console\Request as ConsoleRequest;

class CleanEventsController extends AbstractConsoleController {

	protected $eventStore;

	public function __construct(EventStore $eventStore, EntityManager $entityManager) {
        $this->eventStore = $eventStore;
        $this->entityManager = $entityManager;
    }

    /**
     * checks for every event to find those who break when serialized
     */
	public function cleanAction()
    {
        $repo = $this->entityManager->getRepository(EventStream::class);
        $events = $repo->findAll();

        $count = 0;
        foreach ($events as $event) {
            try {
                $event->serialize();
            } catch (\Exception $e) {
                ++$count;
                dump($event);
                dump($e->getMessage());
            }
        }

        echo 'Eventi corrotti: '.$count;
    }

}


set_error_handler(function($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $filename, $lineno);
    }
});
