<?php

namespace TaskManagement\Processor;

use Accounting\Service\AccountService;
use Application\Service\Processor;
use Doctrine\ORM\EntityManager;
use Application\Entity\User;
use People\Service\OrganizationService;
use TaskManagement\Event\TaskRevertedToAccepted;

class RevertCreditsAssignedProcessor extends Processor
{
    protected $accountService;

    protected $organizationService;

    protected $entityManager;

    public function __construct(AccountService $accountService, OrganizationService $organizationService, EntityManager $em)
    {
        $this->accountService = $accountService;
        $this->organizationService = $organizationService;
        $this->entityManager = $em;
    }

    public function getRegisteredEvents()
    {
        return [
            TaskRevertedToAccepted::class
        ];
    }

    public function handleTaskRevertedToAccepted(TaskRevertedToAccepted $event)
    {
        $by = $this->entityManager
                   ->find(User::class, $event->by());

        $organization = $this->organizationService
                             ->getOrganization($event->organizationId());

        $payee = $this->accountService
                      ->getAccount($organization->getAccountId());

        foreach ($event->assignedCredits() as $memberId => $amount) {

            $account = $this->accountService
                            ->findPersonalAccount($memberId, $event->organizationId());

            $payer = $this->accountService
                          ->getAccount($account->getId());

            $this->accountService
                 ->transfer($payer, $payee, $amount, "Reverted Item '{$event->taskSubject()}' shares", $by);
        }

    }
}