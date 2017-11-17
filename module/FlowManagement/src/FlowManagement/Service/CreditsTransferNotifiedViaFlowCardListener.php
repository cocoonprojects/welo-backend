<?php

namespace FlowManagement\Service;

use Application\Service\UserService;
use Accounting\IncomingCreditsTransferred;
use Accounting\OutgoingCreditsTransferred;
use Doctrine\ORM\EntityManager;
use FlowManagement\Entity\CreditsAddedCard;
use FlowManagement\Entity\CreditsSubtractedCard;
use FlowManagement\FlowCardInterface;
use Accounting\Service\AccountService;
use Rhumsaa\Uuid\Uuid;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;

class CreditsTransferNotifiedViaFlowCardListener implements ListenerAggregateInterface {
	
	protected $listeners = [];

	private $userService;

	private $accountService;

	private $entityManager;

	public function __construct(
        UserService $userService,
        AccountService $accountService,
        EntityManager $entityManager) {

        $this->userService = $userService;
        $this->accountService = $accountService;
        $this->entityManager = $entityManager;
    }
	
	public function attach(EventManagerInterface $events) {

	    $this->listeners[] = $events->getSharedManager()->attach(
		    Application::class,
            IncomingCreditsTransferred::class,
            array($this, 'processIncomingCreditsTransferred')
        );

		$this->listeners[] = $events->getSharedManager()->attach(
		    Application::class,
            OutgoingCreditsTransferred::class,
            array($this, 'processOutgoingCreditsTransferred')
        );
	}

    public function detach(EventManagerInterface $events) {
        foreach ( $this->listeners as $index => $listener ) {
            if ($events->detach ( $listener )) {
                unset ( $this->listeners [$index] );
            }
        }
    }

	public function processIncomingCreditsTransferred(Event $event) {

	    $streamEvent = $event->getTarget();
        $agg_type = $streamEvent->metadata()['aggregate_type'];

        if ($agg_type == 'Accounting\Account') {
            return;
        }

        $data = $streamEvent->payload();

        $by = $this->userService->findUser($data['by']);
        $payerAccount = $this->accountService->findAccount($data['payer']);
        $payer = $payerAccount->holders()->first();
        $org = $payerAccount->getOrganization();

        $flowCard = CreditsSubtractedCard::create(Uuid::uuid4(), $data['amount'], $payer, $org, $by);

        $this->entityManager->persist($flowCard);
        $this->entityManager->flush();
	}
	
	public function processOutgoingCreditsTransferred(Event $event) {

        $streamEvent = $event->getTarget();
        $agg_type = $streamEvent->metadata()['aggregate_type'];

        if ($agg_type == 'Accounting\Account') {
            return;
        }

        $data = $streamEvent->payload();

        $by = $this->userService->findUser($data['by']);
        $payeeAccount = $this->accountService->findAccount($data['payee']);
        $payee = $payeeAccount->holders()->first();
        $org = $payeeAccount->getOrganization();

        $flowCard = CreditsAddedCard::create(Uuid::uuid4(), $data['amount'], $data['source'], $payee, $org, $by);

        $this->entityManager->persist($flowCard);
        $this->entityManager->flush();
    }
	
}