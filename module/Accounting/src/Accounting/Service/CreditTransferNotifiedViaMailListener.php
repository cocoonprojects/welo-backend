<?php

namespace Accounting\Service;

use AcMailer\Service\MailServiceInterface;
use Application\Entity\User;
use Application\Service\FrontendRouter;
use Application\Service\UserService;
use People\Service\OrganizationService;
use People\Entity\Organization;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use Accounting\IncomingCreditsTransferred;
use Accounting\OutgoingCreditsTransferred;

class CreditTransferNotifiedViaMailListener implements ListenerAggregateInterface
{
	private $mailService;

	private $userService;

	private $orgService;

	private $accountService;

	protected $listeners = [];

	protected $feRouter;

	protected $host;
	
	public function __construct(
	    MailServiceInterface $mailService,
        UserService $userService,
        OrganizationService $orgService,
        AccountService $accountService,
        FrontendRouter $feRouter) {

	    $this->mailService = $mailService;
		$this->userService = $userService;
		$this->orgService = $orgService;
		$this->accountService = $accountService;
        $this->feRouter = $feRouter;
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

    public function processOutgoingCreditsTransferred(Event $event){
        $streamEvent = $event->getTarget();
        $agg_type = $streamEvent->metadata()['aggregate_type'];

        if ($agg_type == 'Accounting\Account') {
            return;
        }


        $data = $streamEvent->payload();
        $amount = abs($data['amount']);

        $source = $data['source'];
        $description = $data['description'];

        $by = $this->userService->findUser($data['by']);
        $payeeAccount = $this->accountService->findAccount($data['payee']);

        $payee = $payeeAccount->holders()->first();
        $org = $payeeAccount->getOrganization();

        $this->sendCreditsAddedInfoMail($by, $payee, $amount, $source, $org);
    }

	public function processIncomingCreditsTransferred(Event $event) {

        $streamEvent = $event->getTarget();
        $agg_type = $streamEvent->metadata()['aggregate_type'];

        if ($agg_type == 'Accounting\Account') {
            return;
        }

        $data = $streamEvent->payload();
        $amount = abs($data['amount']);

        $description = $data['description'];

        $by = $this->userService->findUser($data['by']);
        $payerAccount = $this->accountService->findAccount($data['payer']);
        $payer = $payerAccount->holders()->first();
        $org = $payerAccount->getOrganization();

		$this->sendCreditsSubtractedInfoMail($by, $payer, $amount, $org);

	}

	public function sendCreditsSubtractedInfoMail(User $by, User $payer, $amount, Organization $org, $source){

        $message = $this->mailService->getMessage();
        $message->setTo($payer->getEmail());
        $message->setSubject("$amount credits transferred from your account in the '{$org->getName()}' organization'");

        $this->mailService->setTemplate('mail/credits-subtracted.phtml', [
            'recipient' => $payer,
            'by' => $by,
            'amount' => $amount,
            'org' => $org,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);

        $this->mailService->send();

		return $payer;
	}

	public function sendCreditsAddedInfoMail(User $by, User $payee, $amount, $source, Organization $org){

        $message = $this->mailService->getMessage();
        $message->setTo($payee->getEmail());
        $message->setSubject("$amount credits transferred in your account from the '{$org->getName()}' organization'");

        $emailTemplate = 'mail/credits-added.phtml';
        if ($source==\Accounting\Account::CREDITS_FROM_SHARES) {
            $emailTemplate = 'mail/credits-added-for-shares.phtml';
        }

        $this->mailService->setTemplate($emailTemplate, [
            'recipient' => $payee,
            'by' => $by,
            'amount' => $amount,
            'org' => $org,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);

        $this->mailService->send();

		return $payee;
	}

	/**
	 * @return MailServiceInterface
	 */
	public function getMailService() {
		return $this->mailService;
	}

	public function getOrganizationService() {
		return $this->orgService;
	}

	public function setHost($host) {
		$this->host = $host;
		return $this;
	}
}
