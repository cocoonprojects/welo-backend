<?php

namespace TaskManagement\Controller\Console;

use Zend\Mvc\Controller\AbstractConsoleController;
use TaskManagement\Service\TaskService;
use People\Service\OrganizationService;
use TaskManagement\TaskInterface;
use Zend\Console\Request as ConsoleRequest;
use Application\Entity\User;
use Application\Service\UserService;
use Zend\Stdlib\RequestInterface;
use Zend\Stdlib\ResponseInterface;

class SharesClosingController extends AbstractConsoleController {

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

    public function dispatch(RequestInterface $request, ResponseInterface $response = null)
	{
		$request = $this->getRequest();

		$systemUser = $this->userService
						   ->findUser(User::SYSTEM_USER);

		if (!$systemUser) {
			$this->write("missing system user, aborting");

			exit(1);
        }

        $this->write("loaded system user {$systemUser->getEmail()}");

		$orgs = $this->organizationService->findOrganizations();

		foreach($orgs as $org) {

			$this->write("org {$org->getName()} ({$org->getId()})");

            $this->closeAcceptedTasksWithNonCompleteSharesAssignment($systemUser, $org);

			$this->write("");
		}

	}

	private function write($msg)
	{
		$now = (new \DateTime('now'))->format('Y-m-d H:s');

		echo "[$now] ", $msg, "\n";
	}

    private function closeAcceptedTasksWithNonCompleteSharesAssignment($systemUser, $org)
    {
        $timeboxForSharesAssignment = $org->getParams()
            ->get('assignment_of_shares_timebox');

        $this->write("timebox for shares assignment is {$timeboxForSharesAssignment->format('%d')}");

        $itemAccepted = $this->taskService->findAcceptedTasksBefore(
            $timeboxForSharesAssignment,
            $org->getId()
        );

        $totItemIdeas = count($itemAccepted);

        $this->write("found $totItemIdeas accepted items to process");

        if($totItemIdeas == 0) {
            return;
        }

        array_walk($itemAccepted, function($idea) use($systemUser){
            $itemId = $idea->getId();
            $item = $this->taskService->getTask($itemId);

            $membersCount = $item->countMembers();
            $sharesCount = $item->countMembersShare();

            $minimumSharesToAutoClose = min(2, $membersCount);

            if ($sharesCount < $minimumSharesToAutoClose) {
                $this->write("Not enough shares to close the task $itemId: [$sharesCount / $minimumSharesToAutoClose]");
                return;
            }

            $this->transaction()->begin();

            try {
                $this->write("closing task $itemId with $sharesCount shares");
                $item->close($systemUser);
                $this->transaction()->commit();
            }catch (\Exception $e) {
                $this->transaction()->rollback();
                $this->write("error closing task $itemId: {$e->getMessage()}");
            }
        });

    }
}
