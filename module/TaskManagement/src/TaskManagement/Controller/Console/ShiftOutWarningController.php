<?php

namespace TaskManagement\Controller\Console;

use Application\Service\UserService;
use Doctrine\ORM\EntityManager;
use People\Entity\OrganizationMembership;
use Zend\Mvc\Controller\AbstractConsoleController;
use People\Service\OrganizationService;
use Application\Entity\User;

class ShiftOutWarningController extends AbstractConsoleController {

	protected $organizationService;

	protected $userService;

	protected $entityManager;

	public function __construct(OrganizationService $organizationService, UserService $userService, EntityManager $entityManager)
    {
		$this->organizationService = $organizationService;
		$this->userService = $userService;
		$this->entityManager = $entityManager;
	}

    public function sendAction()
	{
        $systemUser = $this->loadSystemUser();

		$orgs = $this->organizationService->findOrganizations();

		foreach($orgs as $org) {

			$this->write("org {$org->getName()} ({$org->getId()})");

            $this->sendShiftOutWarning($systemUser, $org);

			$this->write("");
		}

	}

    private function sendShiftOutWarning($systemUser, $org)
    {
        $params = $org->getParams();

        $shiftout_min_credits = $params->get('shiftout_min_credits') ?: 100;
        $shiftout_min_item = $params->get('shiftout_min_item') ?: 3;
        $shiftout_days = $params->get('shiftout_days') ?: 30;

        $this->write("threshold: {$shiftout_min_item} item; {$shiftout_min_credits} credits; {$shiftout_days} days");


        $memberships = $this->organizationService
                            ->findOrganizationMemberships($org, null, null, [OrganizationMembership::ROLE_MEMBER, OrganizationMembership::ROLE_CONTRIBUTOR, OrganizationMembership::ROLE_ADMIN]);

        $orgAggregate = $this->organizationService
                             ->getOrganization($org->getId());

        foreach($memberships as $member) {
            $user = $member->getMember();

            $this->write($user->getDislayedName() . ' ' . $user->getId());

            if (!$member->joinedMoreThanDaysAgo(30)) {
                continue;
            }

            if ($member->wasWarnedAboutShiftOut()) {
                $this->write("already warned");
                continue;
            }

            if ($this->organizationService->isMemberOverShiftOutQuota($user->getId(), $org->getId(), $shiftout_min_credits, $shiftout_min_item, $shiftout_days)) {

                try {
                    $this->transaction()->begin();

                    $orgAggregate->resetShiftOutWarning($user, $systemUser);

                    $this->write("resetting shiftout warning");

                    $this->transaction()->commit();
                } catch (\Exception $e) {
                    $this->write($e->getMessage());

                    $this->transaction()->rollback();
                }

                continue;
            }

            $contrib = $this->organizationService
                            ->getMemberContributionWithinDays($user->getId(), $org->getId(), $shiftout_days);


            try {
                $this->transaction()->begin();

                $orgAggregate->shiftOutWarning(
                    $user,
                    $contrib['gainedCredits'],
                    $contrib['numItemWorked'],
                    $shiftout_min_credits,
                    $shiftout_min_item,
                    $shiftout_days,
                    $systemUser
                );

                $this->write("sending shiftout warning");

                $this->transaction()->commit();
            } catch (\Exception $e) {
                $this->write($e->getMessage());

                $this->transaction()->rollback();
            }
        }

    }

    /**
     * @return \Application\Service\User
     */
    private function loadSystemUser()
    {
        $systemUser = $this->userService
            ->findUser(User::SYSTEM_USER);

        if (!$systemUser) {
            $this->write('missing system user, aborting');

            exit(1);
        }

        $this->write("loaded system user {$systemUser->getEmail()}");

        return $systemUser;
    }

    private function write($msg)
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:s');

        echo "[$now] ", $msg, "\n";
    }
}
