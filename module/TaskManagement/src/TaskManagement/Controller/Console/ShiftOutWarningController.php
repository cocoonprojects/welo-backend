<?php

namespace TaskManagement\Controller\Console;

use People\Entity\OrganizationMembership;
use Zend\Mvc\Controller\AbstractConsoleController;
use TaskManagement\Service\TaskService;
use People\Service\OrganizationService;
use TaskManagement\TaskInterface;
use Zend\Console\Request as ConsoleRequest;
use Application\Entity\User;
use Application\Service\UserService;
use Zend\Stdlib\RequestInterface;
use Zend\Stdlib\ResponseInterface;

class ShiftOutWarningController extends AbstractConsoleController {

	protected $taskService;

	protected $organizationService;

	protected $userService;

	public function __construct(
		TaskService $taskService,
		OrganizationService $organizationService,
		UserService $userService
	) {
		$this->taskService = $taskService;
		$this->organizationService = $organizationService;
		$this->userService = $userService;
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

        $shiftout_min_credits = $params->get('shiftout_min_credits');
        $shiftout_min_item = $params->get('shiftout_min_item');
        $shiftout_days = $params->get('shiftout_days');

        $this->write("threshold: {$shiftout_min_item} item; {$shiftout_min_credits} credits; {$shiftout_days} days");

        $events = $this->eventStreamRepo
                       ->findByType('TaskManagement\CreditsAssigned', $org->getId(), $shiftout_days);

        $credits = [];

        foreach ($events as $creditsAssigned) {

            foreach ($creditsAssigned->getPayload() as $userId => $userCredits) {

                $credits[$userId]['credits'] = $userCredits;
                $credits[$userId]['numItems'] = $credits[$userId]['numItems'] ? 0 : $credits[$userId]['numItems']++;
            }
        }

        $memberships = $this->organizationService
                            ->findOrganizationMemberships($org, null, null, [OrganizationMembership::ROLE_MEMBER]);

        $orgAggregate = $this->organizationService
                             ->getOrganization($org->getId());

        foreach($memberships as $member) {

            if ($member->wasWarnedAboutShiftOut()) {
                continue;
            }

            $user = $member->getMember();

            if ($this->isMemberOverMinQuota($user, $credits, $shiftout_min_credits, $shiftout_min_item)) {
                continue;
            }

            $orgAggregate->shiftOutWarning($systemUser, $user);
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
            $this->write("missing system user, aborting");

            exit(1);
        }

        $this->write("loaded system user {$systemUser->getEmail()}");

        return $systemUser;
    }

    /**
     * @param $credits
     * @param $user
     * @param $shiftout_min_credits
     * @param $shiftout_min_item
     * @return bool
     */
    private function isMemberOverMinQuota($user, $credits, $shiftout_min_credits, $shiftout_min_item)
    {
        return isset($credits[$user->getId()]) &&
            $credits[$user->getId()]['credits'] >= $shiftout_min_credits &&
            $credits[$user->getId()]['numItems'] >= $shiftout_min_item;
    }

    private function write($msg)
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:s');

        echo "[$now] ", $msg, "\n";
    }
}
