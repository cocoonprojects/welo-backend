<?php

namespace TaskManagement\Service;

use Application\Service\UserService;
use Prooph\EventStore\EventStore;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\Event;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use People\Service\OrganizationService;
use TaskManagement\AcceptanceCreated;
use TaskManagement\Entity\Task as ReadModelTask;
use TaskManagement\Entity\Vote;
use People\Entity\OrganizationMembership;

class AcceptCompletedItemListener implements ListenerAggregateInterface
{
	protected $listeners = array ();

	/**
	 *
	 * @var OrganizationService
	 */
	protected $organizationService;

	/**
	 *
	 * @var TaskService
	 */
	protected $taskService;
	/**
	 *
	 * @var UserService
	 */
	protected $userService;

	/**
	 *
	 * @var EventStore
	 */
	private $transactionManager;

	public function __construct(
		TaskService $taskService,
		UserService $userService,
		OrganizationService $organizationService,
		EventStore $transactionManager)
	{
		$this->taskService = $taskService;
		$this->organizationService = $organizationService;
		$this->transactionManager = $transactionManager;
		$this->userService = $userService;
	}

	public function attach(EventManagerInterface $events) {
		$this->listeners [] = $events->getSharedManager ()->attach ( Application::class, AcceptanceCreated::class, array (
				$this,
				'processEvent'
		) );
	}
	public function processEvent(Event $event) {


		$streamEvent = $event->getTarget ();
		$taskId = $streamEvent->metadata ()['aggregate_id'];
		$task = $this->taskService->getTask ( $taskId );
		$ownerid = $task->getOwner ();
		$owner = $this->userService->findUser ( $ownerid );

		$byId = $event->getParam ( 'by' );
        $operator = $this->userService->findUser ( $byId );

		$organization = $this->organizationService->findOrganization ( $task->getOrganizationId () );
		$memberhipcount = $this->organizationService->countOrganizationMemberships ( $organization,
			[ OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER ] );
		$taskReadModel = $this->taskService->findTask ( $taskId );
		$acceptances = $taskReadModel->getAcceptances ();

		$accept = 0;
		$reject = 0;
		$abstain = 0;

		foreach ( $acceptances as $acceptance ) {
			switch ($acceptance->getVote()->getValue()) {
				case Vote::VOTE_FOR :
					$accept++;
					break;
				case Vote::VOTE_AGAINST :
					$reject++;
					break;
				case Vote::VOTE_ABSTAIN :
					$abstain++;
					break;
			}
		}

        $threshold = $memberhipcount / 2;


        $interval = $organization->getParams()
                                 ->get('completed_item_interval_close_task');

        if ($accept > $threshold) {

			$this->transactionManager->beginTransaction ();
			try {

				$task->accept( $owner, $interval );
				$this->transactionManager->commit ();
			} catch ( \Exception $e ) {
				$this->transactionManager->rollback ();
				throw $e;
			}

		    return;
		}

		if ($reject > $threshold) {

			$task->removeAcceptances($operator);

			$this->transactionManager->beginTransaction ();
			try {
				$task->reopen($operator);
				$this->transactionManager->commit ();
			} catch ( \Exception $e ) {
			    var_dump($e->getTraceAsString());
				$this->transactionManager->rollback ();
				throw $e;
			}

			return;
		}

		if ($memberhipcount == (count ( $acceptances ))) {

			if ($accept > $reject) {

				$this->transactionManager->beginTransaction ();
				try {
					$task->accept( $owner, $interval );
					$this->transactionManager->commit ();
				} catch ( \Exception $e ) {
					$this->transactionManager->rollback ();
					throw $e;
				}
			} else {
				$task->removeAcceptances($operator);

				$this->transactionManager->beginTransaction ();
				try {
					$task->reopen($operator);
					$this->transactionManager->commit ();
				} catch ( \Exception $e ) {
					$this->transactionManager->rollback ();
					throw $e;
				}
			}
		}
	}

	public function detach(EventManagerInterface $events) {
		if ($events->getSharedManager ()->detach ( 'TaskManagement\TaskService', $this->listeners [0] )) {
			unset ( $this->listeners [0] );
		}
	}
}