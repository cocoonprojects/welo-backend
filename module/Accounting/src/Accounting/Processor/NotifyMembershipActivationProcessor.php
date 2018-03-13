<?php

namespace Accounting\Processor;

use AcMailer\Service\MailService;
use Application\Service\Processor;
use Application\Service\UserService;
use Doctrine\ORM\EntityManager;
use Application\Entity\User;
use People\Entity\Organization;
use People\Entity\OrganizationMembership;
use People\Event\OrganizationMemberActivationChanged;
use People\Service\OrganizationService;

class NotifyMembershipActivationProcessor extends Processor
{
    protected $mailService;

    protected $organizationService;

    protected $userService;

    public function __construct(OrganizationService $organizationService, UserService $userService, MailService $mailService)
    {
        $this->mailService = $mailService;
        $this->organizationService = $organizationService;
        $this->userService = $userService;
    }

    public function getRegisteredEvents()
    {
        return [
            OrganizationMemberActivationChanged::class
        ];
    }

    public function handleOrganizationMemberActivationChanged(OrganizationMemberActivationChanged $event)
    {
        $by = $this->userService
                   ->findUser($event->by());

        $memberChanged = $this->userService
                   ->findUser($event->userId());

        $organization = $this->organizationService
                             ->findOrganization($event->organizationId());

        $members = $this->organizationService->findOrganizationMemberships($organization, 9999, 0, [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER]);
        foreach ($members as $memberId => $member) {
            $this->sendOrganizationMemberActivationChangedMail($organization, $memberChanged, $event->by(), $members, $event->active());
        }
    }


    public function sendOrganizationMemberActivationChangedMail(Organization $org, User $changedMember, User $by, $members, $newStatus)
    {
        $rv = [];
        if ($newStatus) {
            $emailTemplate = 'mail/member-activated.phtml';
            $title = "The user {$changedMember->getFirstname()} {$changedMember->getLastname()} has been activated";
        } else {
            $emailTemplate = 'mail/member-deactivated.phtml';
            $title = "The user {$changedMember->getFirstname()} {$changedMember->getLastname()} has been deactivated";
        }

        foreach ($members as $member) {
            $recipient = $member->getMember();

            $message = $this->mailService->getMessage();
            $message->setTo($recipient->getEmail());
            $message->setSubject($title);

            $this->mailService->setTemplate($emailTemplate, [
                'member' => $changedMember,
                'recipient'=> $recipient,
                'by'=> $by,
                'organization'=> $org
            ]);
            $this->mailService->send();
            $rv[] = $recipient;
        }
        return $rv;

    }

}