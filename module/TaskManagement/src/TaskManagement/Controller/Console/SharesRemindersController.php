<?php

namespace TaskManagement\Controller\Console;

use Application\Service\FrontendRouter;
use Zend\Mvc\Controller\AbstractConsoleController;
use TaskManagement\Service\TaskService;
use People\Service\OrganizationService;
use AcMailer\Service\MailService;

class SharesRemindersController extends AbstractConsoleController {

    protected $taskService;
    protected $organizationService;
    protected $host;
    protected $mailService;
    protected $feRouter;

    public function __construct(
        TaskService $taskService,
        MailService $mailService,
        OrganizationService $organizationService,
        FrontendRouter $frontendRouter)
    {
        $this->taskService = $taskService;
        $this->organizationService = $organizationService;
        $this->mailService = $mailService;
        $this->feRouter = $frontendRouter;
    }

    public function setHost($host) {
        $this->host = $host;
        return $this;
    }

    /**
     * @param array $data
     * @return \Zend\Stdlib\ResponseInterface
     */
    public function sendAction()
    {
        $orgs = $this->organizationService->findOrganizations();

        foreach($orgs as $org) {

            $intervalForSharesRemind = $org->getParams()
                ->get('assignment_of_shares_remind_interval');

            $intervalForSharesTimebox = $org->getParams()
                ->get('assignment_of_shares_timebox');

            $this->write("loading org {$org->getName()} ({$org->getId()})");
            $this->write("shares assignment remind interval is {$intervalForSharesRemind->format('%d')}");
            $this->write("shares assignment timebox is {$intervalForSharesTimebox->format('%d')}");

            $tasksToNotify = $this->taskService
                ->findAcceptedTasksBetween(
                    $intervalForSharesTimebox,
                    $intervalForSharesRemind,
                    $org->getId()
                );

            $totTasks = count($tasksToNotify);

            $this->write("found $totTasks tasks");

            foreach ($tasksToNotify as $task) {

                $taskMembersWithNoShares = $task->findMembersWithEmptyShares();

                $totTasksMembers = count($taskMembersWithNoShares);

                $this->write("task {$task->getId()} has $totTasksMembers to be notified");

                foreach ($taskMembersWithNoShares as $tm){

                    $member = $tm->getUser();
                    $message = $this->mailService->getMessage();
                    $message->setTo($member->getEmail());
                    $message->setSubject('Assign shares for "'.$task->getSubject().'" item');

                    $this->mailService->setTemplate('mail/reminder-assignment-shares.phtml', [
                        'task' => $task,
                        'recipient'=> $member,
                        'host' => $this->host,
                        'router' => $this->feRouter
                    ]);

                    $this->mailService->send();

                    $this->write("{$member->getEmail()} notified (task {$task->getId()})");
                }
            }

            $this->write("");

        }
    }

    private function write($msg)
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:s');

        echo "[$now] ", $msg, "\n";
    }

}