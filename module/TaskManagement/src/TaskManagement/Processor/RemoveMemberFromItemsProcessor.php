<?php

namespace TaskManagement\Processor;

use Application\Entity\User;
use Application\Service\Processor;
use Application\Service\UserService;
use Doctrine\ORM\EntityManager;
use People\Event\OrganizationMemberRemoved;
use People\Service\OrganizationService;
use Prooph\EventStore\EventStore;
use TaskManagement\Service\TaskService;

class RemoveMemberFromItemsProcessor extends Processor
{
    protected $taskService;

    protected $userService;

    protected $orgService;

    protected $entityManager;

    protected $eventStore;


    public function __construct(TaskService $taskService, UserService $userService, OrganizationService $orgService, EntityManager $em, EventStore $es)
    {
        $this->taskService = $taskService;
        $this->userService = $userService;
        $this->orgService = $orgService;
        $this->entityManager = $em;
        $this->eventStore = $es;
    }


    public function getRegisteredEvents()
    {
        return [
            OrganizationMemberRemoved::class
        ];
    }


    public function handleOrganizationMemberRemoved(OrganizationMemberRemoved $event)
    {
        $orgId = $event->aggregateId();
        $org = $this->orgService->getOrganization($orgId);
        $memberId = $event->userId();
        $member = $this->userService->findUser($memberId);
        $by = $event->by();

        $tasksReadModels = $this->taskService->findTasks($org, 0, 99999, [ 'memberId' => $memberId ] );

        foreach ($tasksReadModels as $taskReadModel) {
            $this->eventStore->beginTransaction();

            try {
                $task = $this->taskService->getTask($taskReadModel->getId());
                $task->removeMember($member, $by);

                $this->eventStore->commit();

            } catch (\Exception $e) {
                $this->eventStore->rollback();
                throw $e;
            }

        }

    }


    protected function updatePositionsForItemsInPreviousLane($organizationId, $previousPosition, $previousLane, $by)
    {
        $tasksReadModel = $this->taskService
                               ->findTasksInLaneAfter($organizationId, $previousLane, $previousPosition);

        $updatedPosition = $previousPosition;

        foreach ($tasksReadModel as $taskReadModel) {

            $this->eventStore->beginTransaction();

            try {
                $task = $this->taskService->getTask($taskReadModel->getId());

                $task->setPosition($updatedPosition, $by);
                $this->eventStore->commit();

            } catch (\Exception $e) {
                $this->eventStore->rollback();
                throw $e;
            }

            $updatedPosition++;
        }
    }
}