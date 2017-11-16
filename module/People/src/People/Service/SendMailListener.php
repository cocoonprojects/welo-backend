<?php

namespace People\Service;

use Application\Service\FrontendRouter;
use People\Entity\OrganizationMembership;
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
            $orgReadModel = $this->organizationService->findOrganization($organizationId);

            $orgMemberships = $this->organizationService
                                   ->findOrganizationMemberships(
                                        $orgReadModel,
                                        null,
                                        null,
                                        [
                                            OrganizationMembership::ROLE_ADMIN,
                                            OrganizationMembership::ROLE_MEMBER
                                        ]
                                   );

            $member = $this->userService->findUser($userId);

            $orgMembers = [];
            foreach ($orgMemberships as $orgMembership) {

                $user = $orgMembership->getMember();
                $orgMembers[] = $user;
            }

            $minCredits = $streamEvent->payload()['minCredits'];
            $minItems = $streamEvent->payload()['minItems'];
            $withinDays = $streamEvent->payload()['withinDays'];

			$this->sendShiftOutWarningToUser($organization, $member, $minCredits, $minItems, $withinDays);
			$this->sendShiftOutWarningToOrg($organization, $member, $orgMembers, $minCredits, $minItems, $withinDays);
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

	public function sendShiftOutWarningToUser(Organization $organization, User $member, $minCredits, $minItems, $withinDays)
	{
        $message = $this->mailService->getMessage();
        $message->setTo($member->getEmail());
        $message->setSubject('Your contribution in the ' . $organization->getName() . ' open governance');

        $this->mailService->setTemplate( 'mail/shiftout-warning.phtml', [
            'member' => $member,
            'minCredits' => $minCredits,
            'minItems' => $minItems,
            'withinDays' => $withinDays,
            'organization' => $organization,
        ]);

        $this->mailService->send();
	}

	public function sendShiftOutWarningToOrg(Organization $organization, User $member, array $orgMembers, $minCredits, $minItems, $withinDays)
	{
        $message = $this->mailService->getMessage();

        foreach ($orgMembers as $orgMember) {

            $message->setTo($orgMember->getEmail());
            $message->setSubject($member->getDislayedName() . ' contribution in the ' . $organization->getName() . ' open governance');

            $this->mailService->setTemplate( 'mail/shiftout-warning-org.phtml', [
                'member' => $member,
                'minCredits' => $minCredits,
                'minItems' => $minItems,
                'withinDays' => $withinDays,
                'organization' => $organization,
            ]);

            $this->mailService->send();

        }


	}
}