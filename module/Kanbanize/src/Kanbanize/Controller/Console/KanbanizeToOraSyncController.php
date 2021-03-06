<?php

namespace Kanbanize\Controller\Console;

use Kanbanize\Service\OperationFailedException;
use Zend\Mvc\Controller\AbstractConsoleController;
use Zend\Console\Request as ConsoleRequest;

use Application\Entity\User;
use Application\Service\UserService;
use People\Service\OrganizationService;
use People\Organization;
use TaskManagement\Service\TaskService;
use TaskManagement\TaskInterface;
use Kanbanize\Service\KanbanizeService;
use Kanbanize\Service\NotificationService;

/**
 * assumptions:
 * - one board is bound to one organization
 */
class KanbanizeToOraSyncController extends AbstractConsoleController {

    CONST API_URL_FORMAT = "https://%s.kanbanize.com/index.php/api/kanbanize";

    protected $taskService;

    protected $organizationService;

    protected $userService;

    protected $kanbanizeService;

    protected $mailService;

    protected $orgsErrors = [];

    protected $tasksErrors = [];

    public function __construct(
        TaskService $taskService,
        OrganizationService $organizationService,
        UserService $userService,
        KanbanizeService $kanbanizeService,
        NotificationService $mailService

    ) {
        $this->taskService = $taskService;
        $this->organizationService = $organizationService;
        $this->userService = $userService;
        $this->kanbanizeService = $kanbanizeService;
        $this->mailService = $mailService;
    }

    public function syncAction()
    {
        $features = $this->serviceLocator->get('configuration')['features'];

        $request = $this->getRequest();
        $this->assertIsConsoleRequest($request);

        $systemUser = $this->userService
            ->findUser(User::SYSTEM_USER);

        $this->assertIsSystemUser($systemUser);

        $orgs = $this->organizationService
            ->findOrganizations();

        $this->write("SYNC START");

        foreach ($orgs as $org) {

            $orgId = $org->getId();

            $this->write('-------------------');
            $this->write("org {$org->getName()} ({$orgId})");

            $stream = $this->kanbanizeService
                ->findStreamByOrganization($org);

            if (!$stream || !$stream->isBoundToKanbanizeBoard()) {
                continue;
            }

            $kanbanize = $org->getSettings(Organization::KANBANIZE_SETTINGS);

            $this->kanbanizeService
                ->initApi($kanbanize['apiKey'], $kanbanize['accountSubdomain']);

            try {

                $this->kanbanizeService->loadLanesFromKanbanize($stream->getBoardId());

                $lanes = $this->getLanesDiffWithKanbanize(
                    $this->kanbanizeService->getFullBoardStructure($stream->getBoardId()),
                    $org
                );

            } catch (\Exception $e) {
                $error = "ERROR {$e->getMessage()}";

                $this->write($error);
                $this->pushOrgError($orgId, $error);

                continue;
            }


            $lanes['app'] = $this->updateLanesNames($lanes['app'], $lanes['kanbanize']);
            $lanes['app'] = $this->addLanes($lanes['app'], $lanes['toAdd']);
            $lanes['app'] = $this->removeLanes($lanes['app'], $lanes['toRemove']);


            try {
                $this->transaction()->begin();

                $organization = $this->organizationService->getOrganization($orgId);
                $organization->setLanes($lanes['app'], $systemUser);

                $this->transaction()->commit();

                $this->write('saved lanes: ' . str_replace(PHP_EOL, '', var_export($lanes['app'], 1)));

            } catch (\Exception $e) {
                $error = "ERROR updating organization {$organization->getId()} lanes";
                $this->write($error);
                $this->pushOrgError($orgId, $error);

                $this->transaction()->rollback();
            }

            $this->write("organization {$organization->getId()} lanes UPDATED");


            $this->write("loading board activities stream {$stream->getId()} board {$stream->getBoardId()}");

            $kanbanizeTasks = $this->kanbanizeService
                ->getTasks($stream->getBoardId());

            //when something goes wrong a string is returned
            if (is_string($kanbanizeTasks)) {
                $this->write($kanbanizeTasks);
                $this->pushOrgError($orgId, $kanbanizeTasks);
                continue;
            }

            $mapping = $kanbanize['boards'][$stream->getBoardId()]['columnMapping'];

            if ($this->isMappingChanged($stream->getBoardId(), $mapping)) {

                $this->sendAlertEmail($org);

                continue;
            }

            foreach ($kanbanizeTasks as $kanbanizeTask) {
                $kanbanizeTaskId = $kanbanizeTask['taskid'];
                $this->write("task $kanbanizeTaskId");

                $task = $this->taskService
                    ->findTaskByKanbanizeId($kanbanizeTaskId);

                if (!$task) {
                    try {
                        $this->blockTaskOnKanbanize($kanbanizeTask);
                    } catch (\Exception $e) {
                        $this->write("[ERROR] generic exception raised syncing with kanbanize: " . $e->getMessage());
                        $this->pushOrgError($orgId, "ERROR trying to block task $kanbanizeTaskId on Kanbanize " . $e->getMessage());
                    }
                    continue;
                }

                try {
                    $this->unblockTaskOnKanbanize($kanbanizeTask);

                    $this->fixColumnOnKanbanize(
                        $task,
                        $kanbanizeTask,
                        $stream->getBoardId(),
                        $mapping
                    );

                    $this->updateTaskOnKanbanize(
                        $task,
                        $kanbanizeTask,
                        $stream
                    );
                } catch (OperationFailedException $e) {
                    $this->write("ERROR trying to unblock item {$task->getId()} on Kanbanize: " . $e->getMessage());
                    $this->pushTaskError($orgId, $task->getId(), "ERROR trying to unblock item {$task->getId()} on Kanbanize: " . $e->getMessage());
                } catch (\Exception $e) {
                    $this->write("ERROR updating item with id {$task->getId()} on Kanbanize: " . $e->getMessage());
                    $this->pushTaskError($orgId, $task->getId(), "ERROR updating item with id {$task->getId()} on Kanbanize: " . $e->getMessage());
                }

                $this->updateTaskPositionFromKanbanize(
                    $task,
                    $kanbanizeTask,
                    $systemUser
                );
            }

            $this->write("");
        }
        $this->write("SYNC END");

        if ($features['send_emails_on_kanbanize_sync_errors']=='true') {
            $this->notifyErrors($systemUser);
        }
    }

    private function notifyErrors($systemUser)
    {
        $this->write("SEND ERRORS EMAILS");

        $allOrgs = $this->organizationService->findOrganizations();

        foreach ($allOrgs as $org) {

            $this->write('org ' . $org->getId());

            $orgAggregate = $this->organizationService->getOrganization($org->getId());

            // first case: no error && flag set -> sync successful after an error
            // - unset flag
            // - do not send email
            if (!$this->hasErrors($org->getId())) {
                if ($orgAggregate->hasSyncErrorsNotificationSet()) {
                    try {

                        $this->transaction()->begin();

                        $orgAggregate->clearSyncErrorsNotification($systemUser);
                        $this->write('clear sync error flag');
                        $this->transaction()->commit();

                    } catch (\Exception $e) {
                        $error = "ERROR updating organization {$orgAggregate->getId()} sync warn";
                        $this->write($error);
                        $this->transaction()->rollback();
                    }
                }

                continue;
            }

            // second case: sync error && flag set -> sync unsuccessful but an email was already sent
            // do nothing
            if ($orgAggregate->hasSyncErrorsNotificationSet()) {
                $this->write('error + sync error flag, do nothing');

                continue;
            }

            // third case: sync error && flag not set -> sync unsuccessful, need to warn users
            // - set the flag
            // - set it and send email

            try {

                $this->transaction()->begin();

                $orgAggregate->setSyncErrorsNotification($systemUser);
                $this->write('set sync error flag');

                $this->transaction()->commit();

            } catch (\Exception $e) {
                $error = "ERROR updating organization {$orgAggregate->getId()} sync warn";
                $this->write($error);
                $this->transaction()->rollback();
            }

            if (!$this->hasErrors($org->getId())) {
                continue;
            }

            $orgErrors = $this->getOrgsErrors($org->getId());
            $tasksErrors = $this->getTasksErrorsByOrg($org->getId());

            $this->write("   org [".$org->getId()."] ".$org->getName());

            if (!empty($orgErrors)) {
                $this->write(">>> Org errors:");
                $this->write(print_r($orgErrors, true));
            }

            if (!empty($tasksErrors)) {
                $this->write(">>> Tasks errors:");
                $this->write(print_r($tasksErrors, true));
            }

            $this->mailService
                ->sendKanbanizeSyncErrors($org, $orgErrors, $tasksErrors);

        }

        $this->write('ERRORS EMAILS SENT');
    }



    private function write($msg)
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:s');

        echo "[$now] ", $msg, "\n";
    }

    private function pushOrgError($orgId, $error)
    {
        if (!isset($this->orgsErrors[$orgId])) {
            $this->orgsErrors[$orgId] = [];
        }

        array_push($this->orgsErrors[$orgId], $error);
    }

    private function pushTaskError($orgId, $taskId, $error)
    {
        if (!is_array($this->tasksErrors[$orgId])) {
            $this->tasksErrors[$orgId] = [];
        }

        if (!is_array($this->tasksErrors[$orgId][$taskId])) {
            $this->tasksErrors[$orgId][$taskId] = [];
        }

        array_push($this->orgsErrors[$orgId][$taskId], $error);
    }

    private function hasErrors($orgId)
    {
        return isset($this->orgsErrors[$orgId]) || isset($this->tasksErrors[$orgId]);
    }

    private function getOrgsErrors($orgId)
    {
        if (!isset($this->orgsErrors[$orgId])) {
            return [];
        }

        return $this->orgsErrors[$orgId];
    }

    private function getTasksErrorsByOrg($orgId)
    {
        if (!isset($this->tasksErrors[$orgId])) {
            return [];
        }

        return $this->tasksErrors[$orgId];
    }

    private function assertIsConsoleRequest($request)
    {
        if (!$request instanceof ConsoleRequest) {
            $this->write("use only from a console!");

            exit(1);
        }
    }

    private function assertIsSystemUser($systemUser)
    {
        if (!$systemUser) {
            $this->write("missing system user, aborting");

            exit(1);
        }

        $this->write("loaded system user {$systemUser->getFirstname()}");
    }

    /**
     * Checks if the columns of the kanban board differ from the current
     * column -> status mapping
     */
    private function isMappingChanged($boardId, $mapping)
    {
        $board = $this->kanbanizeService
                      ->getBoardStructure($boardId);

        if (!is_array($board) && !isset($board['columns'])) {
            $this->write("  error retrieving board $boardId");

            return false;
        }

        $mappedColumns = array_keys($mapping);
        $kanbanizeColumns = array_column($board['columns'], 'lcname');

        if (is_null($kanbanizeColumns)) {
            return false;
        }

        array_pop($kanbanizeColumns); //removes temp archive column

        if ($mappedColumns == $kanbanizeColumns) {
            return false;
        }

        $this->write("  mapping changed");

        return true;
    }

    private function sendAlertEmail($organization)
    {
        $this->mailService
             ->sendKanbanizeSyncAlert($organization);
    }



    /**
     * first case: task on kanbanize but not on Welo
     * block task
     */
    private function blockTaskOnKanbanize($kanbanizeTask)
    {
        if ($kanbanizeTask['blocked']) {
            return;
        }

        $result = $this->kanbanizeService
                        ->blockTask(
                                $kanbanizeTask['boardparent'],
                                $kanbanizeTask['taskid'],
                                'task not on Welo'
        );

        $this->write("  try to block it: $result");
    }

    /**
     * first case: task on kanbanize but not on Welo
     * block task
     */
    private function unblockTaskOnKanbanize($kanbanizeTask)
    {
        if (!$kanbanizeTask['blocked']) {
            return;
        }

        $result = $this->kanbanizeService
                        ->unblockTask(
                                $kanbanizeTask['boardparent'],
                                $kanbanizeTask['taskid'],
                                'task must be unblocked'
        );

        $result = ($result!=1) ? 'ok' : 'error '.$result;
        $this->write("  try to unblock it: $result");
    }

    /**
     * move task to a column matching its status
     */
    private function fixColumnOnKanbanize($task, $kanbanizeTask, $boardId, $mapping)
    {
        if ($mapping[$kanbanizeTask['columnname']] == $task->getStatus()) {
            return;
        }

        $rightColumn = array_search($task->getStatus(), $mapping);

        $result = '';
        try {
            $result = $this->kanbanizeService
                           ->moveTaskonKanbanize(
                                  $task,
                                  $rightColumn,
                                  $boardId
            );

        } catch (Exception $e) {

        } finally {
            $this->write("  try move it to '$rightColumn': $result");
        }
    }

    /**
     * update kanbanize task data based on O.R.A
     */
    private function updateTaskOnKanbanize($task, $kanbanizeTask, $stream)
    {
        if (!$task->isUpdatedBefore(new \DateTime($kanbanizeTask['updatedat']))) {
            return;
        }

        $result = $this->kanbanizeService
                       ->updateTask(
                             $task,
                             $kanbanizeTask,
                             $stream->getBoardId());

        $result = ($result!=1) ? 'ok' : 'error '.$result;
        $this->write("  try update it: ".$result);
    }

    private function updateTaskPositionFromKanbanize($task, $kanbanizeTask, $systemUser)
    {
        //position makes sense only for open tasks
        if ($task->getStatus() != TaskInterface::STATUS_OPEN) {
            return;
        }

        //adjust range
        $position = $kanbanizeTask['position'] +1;

        if ($position == $task->getPosition()) {
            return;
        }

        $this->transaction()->begin();

        try {
            $taskAggregate = $this->taskService
                                  ->getTask($task->getId());

            $taskAggregate->setPosition($position, $systemUser);

            $this->transaction()->commit();

        } catch (Exception $e) {
            $this->transaction()->rollback();
        }
    }

    public function getLanesDiffWithKanbanize($kanbanizeFullStructure, $org)
    {
        $kanbanizeLanes = [];
        foreach ($kanbanizeFullStructure['lanes'] as $lane) {
            $kanbanizeLanes[$lane['lcid']] = $lane['lcname'];
        }
        $appLanes = $org->getLanes();

        $keysAddedInKanbanize = array_diff(array_keys($kanbanizeLanes), array_keys($appLanes));
        $addedInKanbanize = [];
        foreach ($keysAddedInKanbanize as $key) {
            $addedInKanbanize[$key] = $kanbanizeLanes[$key];
        }

        $keysRemovedInKanbanize = array_diff(array_keys($appLanes), array_keys($kanbanizeLanes));
        $removedInKanbanize = [];
        foreach ($keysRemovedInKanbanize as $key) {
            $removedInKanbanize[$key] = $appLanes[$key];
        }

        $this->write( count($kanbanizeLanes) . ' lanes found into the Kanbanize');
        $this->write( count($appLanes) . ' lanes found into the application');
        $this->write( count($keysAddedInKanbanize) . ' lanes will be added into the application');
        $this->write( count($keysRemovedInKanbanize) . ' lanes will be possibly removed from application');

        return [
            'app' => $appLanes,
            'kanbanize' => $kanbanizeLanes,
            'toAdd' => $addedInKanbanize,
            'toRemove' => $removedInKanbanize
        ];
    }

    /**
     * @param $lanesToAdd
     * @return array
     */
    public function updateLanesNames($appLanes, $kanbanizeLanes)
    {
        foreach ($appLanes as $laneId => $laneName) {
            if (isset($kanbanizeLanes[$laneId]) && $appLanes[$laneId] != $kanbanizeLanes[$laneId]) {
                $appLanes[$laneId] = $kanbanizeLanes[$laneId];
                $this->write('updating #' . $laneId . ' lane');
            }
        }
        return $appLanes;
    }

    /**
     * @param $lanesToAdd
     * @return array
     */
    public function addLanes($appLanes, $lanesToAdd)
    {
        foreach ($lanesToAdd as $laneId => $laneName) {
            $this->write('adding "' . $laneName . '" lane into the application');
            $appLanes[$laneId] = $laneName;
        }
        return $appLanes;
    }

    /**
     * @param $lanes
     */
    public function removeLanes($appLanes, $lanesToRemove)
    {
        foreach ($lanesToRemove as $laneId => $laneName) {
            if (!$this->taskService->countItemsInLane($laneId)) {
                $this->write('removing "' . $laneName . '" lane from the application');
                unset($appLanes[$laneId]);
            } else {
                $this->write('unable to remove "' . $laneName . '" lane from the application because has items associated');
            }
        }
        return $appLanes;
    }
}