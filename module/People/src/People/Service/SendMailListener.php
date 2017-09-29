<?php

namespace People\Service;

use Application\Service\FrontendRouter;
use People\Organization;
use People\OrganizationMemberAdded;
use People\ShiftOutWarning;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\Event;
use Zend\EventManager\ListenerAggregateInterface;
use AcMailer\Service\MailService;
use Application\Service\UserService;
use Application\Entity\User;
use Zend\Mvc\Application;

class SendMailListener implements ListenerAggregateInterface
{
	/**
	 * @var MailService
	 */
	private $mailService;
	/**
	 * @var UserService
	 */
	private $userService;
	/**
	 * @var OrganizationService
	 */
	private $organizationService;

	private $feRouter;

	protected $listeners = array ();

	public function __construct(
	    MailService $mailService,
        UserService $userService,
        OrganizationService $organizationService,
        FrontendRouter $feRouter) {

	    $this->mailService = $mailService;
		$this->userService = $userService;
		$this->organizationService = $organizationService;
		$this->feRouter = $feRouter;

	}

	public function attach(EventManagerInterface $events) {
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, OrganizationMemberAdded::class, function (Event $event) {
			$streamEvent = $event->getTarget();
			$organizationId = $streamEvent->metadata()['aggregate_id'];
			$organization = $this->organizationService->getOrganization($organizationId);
			$memberId = $event->getParam ( 'userId' );
			$member = $this->userService->findUser($memberId);
			$this->sendMemberAddedInfoMail ( $organization, $member );
		} );

		$this->listeners[] = $events->getSharedManager()->attach(Application::class, ShiftOutWarning::class, function (Event $event) {
			$streamEvent = $event->getTarget();

			$userId = $streamEvent->payload()['userId'];
            $organizationId = $streamEvent->payload()['organizationId'];

            $organization = $this->organizationService->getOrganization($organizationId);
			$member = $this->userService->findUser($userId);

            $gainedCredits = $streamEvent->payload()['gainedCredits'];
            $numItemWorked = $streamEvent->payload()['numItemWorked'];
            $minCredits = $streamEvent->payload()['minCredits'];
            $minItems = $streamEvent->payload()['minItems'];
            $withinDays = $streamEvent->payload()['withinDays'];

			$this->sendShiftOutWarning($organization, $member, $gainedCredits, $numItemWorked, $minCredits, $minItems, $withinDays);
		} );
	}

	public function detach(EventManagerInterface $events) {
		foreach ( $this->listeners as $index => $listener ) {
			if ($events->detach ( $listener )) {
				unset ( $this->listeners [$index] );
			}
		}
	}

	public function sendMemberAddedInfoMail(Organization $organization, User $member)
	{
		$this->mailService->setSubject ( 'A new member joined "' . $organization->getName() . '"');
		$message = $this->mailService->getMessage();

		$admins = $organization->getAdmins();
		foreach ($admins as $id => $profile) {

			if($id == $member->getId()) {
				continue;
			}

			$recipient = $this->userService->findUser($id);

			$this->mailService->setTemplate( 'mail/new-member-info.phtml', array(
				'recipient' => $recipient,
				'member'=> $member,
				'organization'=> $organization,
                'router' => $this->feRouter
			));

			$message->setTo($recipient->getEmail());

			$this->mailService->send();
		}
	}

	public function sendShiftOutWarning(Organization $organization, User $member, $gainedCredits, $numItemWorked, $minCredits, $minItems, $withinDays)
	{
        $message = $this->mailService->getMessage();
        $message->setTo($member->getEmail());
        $message->setSubject('Hey ' . $member->getDislayedName() . ' it\'s quite been some time');

        $this->mailService->setTemplate( 'mail/shiftout-warning.phtml', [
            'member' => $member,
            'gainedCredits' => $gainedCredits,
            'numItemWorked' => $numItemWorked,
            'minCredits' => $minCredits,
            'minItems' => $minItems,
            'withinDays' => $withinDays,
            'organization' => $organization,
        ]);

        $this->mailService->send();
	}
}