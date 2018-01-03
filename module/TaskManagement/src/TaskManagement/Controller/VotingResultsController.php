<?php

namespace TaskManagement\Controller;

use ZFX\Rest\Controller\HATEOASRestfulController;
use TaskManagement\Service\TaskService;
use People\Service\OrganizationService;
use TaskManagement\TaskInterface;

class VotingResultsController extends HATEOASRestfulController {

	protected static $collectionOptions = ['POST'];
	protected static $resourceOptions = [];

	protected $taskService;

	protected $organizationService;

	public function __construct(
		TaskService $taskService,
		OrganizationService $organizationService
	) {
		$this->taskService = $taskService;
		$this->organizationService = $organizationService;
	}

	public function create($data){
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$type = $this->params('type');
		if (empty($type) && !empty($data['type'])) {
			$type = $data['type'];
		}

		$org = $this->organizationService
					->findOrganization($this->params('orgId'));

		if (!$org) {
			$this->response->setStatusCode(404);
			return $this->response;
		}

		switch ($type) {
			case "idea-items":
				if(!$this->isAllowed($this->identity(), NULL, 'TaskManagement.Task.close-voting-idea-items')){
					$this->response->setStatusCode(403);
					return $this->response;
				}

				$timeboxForVoting = $org->getParams()
										->get('item_idea_voting_timebox');

				$itemIdeas = $this->taskService
					->findItemsCreatedBefore($timeboxForVoting, TaskInterface::STATUS_IDEA);

				if(sizeof($itemIdeas) > 0){
					array_walk($itemIdeas, function($idea) use ($org) {
						$itemId = $idea->getId();
						$results = $this->taskService
                                        ->countVotesForIdeaApproval(TaskInterface::STATUS_IDEA, $itemId);

						$item = $this->taskService->getTask($itemId);
						$this->transaction()->begin();
						try {
							if($results['votesFor'] > $results['votesAgainst']){
								$item->open($this->identity());

                                $position = $this->taskService->getNextOpenTaskPosition($org->getId());
                                $item->setPosition($position, $this->identity());
							}else{
								$item->reject($this->identity());
							}
							$this->transaction()->commit();
						}catch (\Exception $e) {
							$this->transaction()->rollback();
							$this->response->setStatusCode(500);
							return $this->response;
						}
					});
				}
				$this->response->setStatusCode(200);
				break;
			case "completed-items":
				if(!$this->isAllowed($this->identity(), NULL, 'TaskManagement.Task.close-voting-completed-items')){
					$this->response->setStatusCode(403);
					return $this->response;
				}

				$timeboxForVoting = $org->getParams()
										->get('completed_item_voting_timebox');

				$itemsCompleted = $this->taskService
					->findItemsCompletedBefore($timeboxForVoting, $org->getId());

				$operationResult = [];
				if(sizeof($itemsCompleted) > 0){
					array_walk($itemsCompleted, function($completed) use (&$operationResult) {
						$itemId = $completed->getId();
						$results = $this->taskService
                                        ->countVotesForItemAcceptance(TaskInterface::STATUS_COMPLETED, $itemId);

						$item = $this->taskService->getTask($itemId);

						$this->transaction()->begin();
						try {
							if($results['votesFor'] > $results['votesAgainst']){
								$item->accept($this->identity());
								$operationResult[$itemId] = 'closed';
							}else{
								$item->reopen($this->identity());
								$operationResult[$itemId] = 'reopened';
							}
							$this->transaction()->commit();
						}catch (\Exception $e) {
							$this->transaction()->rollback();
							$this->response->setStatusCode(500);
							return $this->response;
						}
					});
				}
				$this->response->setStatusCode(200);
				break;
			default:
				$this->response->setStatusCode(404);
		}
		return $this->response;
	}

	public function getTaskService(){
		return $this->taskService;
	}
	protected function getCollectionOptions(){
		return self::$collectionOptions;
	}

	protected function getResourceOptions(){
		return self::$resourceOptions;
	}
}
