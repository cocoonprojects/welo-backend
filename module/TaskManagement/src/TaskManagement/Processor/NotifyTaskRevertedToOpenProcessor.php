<?php

namespace TaskManagement\Processor;

use AcMailer\Service\MailService;
use Application\Entity\User;
use Application\Service\FrontendRouter;
use Application\Service\Processor;
use Doctrine\ORM\EntityManager;
use People\Entity\OrganizationMembership;
use People\Service\OrganizationService;
use TaskManagement\Service\TaskService;
use TaskManagement\TaskRevertedToOpen;
use Zend\EventManager\Event;

class NotifyTaskRevertedToOpenProcessor extends Processor
{
    protected $organizationService;

    protected $taskService;

    protected $entityManager;

    public function __construct(OrganizationService $organizationService, TaskService $taskService, MailService $mailService, EntityManager $em, FrontendRouter $feRouter)
    {
        $this->organizationService = $organizationService;
        $this->taskService = $taskService;
        $this->mailService = $mailService;
        $this->entityManager = $em;
        $this->feRouter = $feRouter;
    }

    public function getRegisteredEvents()
    {
        return [
            TaskRevertedToOpen::class
        ];
    }

    public function handleTaskRevertedToOpen(Event $event)
    {
        $streamEvent = $event->getTarget();
        $taskId = $streamEvent->metadata()['aggregate_id'];
        $task = $this->taskService
                    ->findTask($taskId);
        $organization = $this->organizationService->findOrganization($task->getOrganizationId());

        if (!$organization->getParams()->get('manage_priorities')) {
            return;
        }

        $executedById = $event->getParam ( 'by' );
        $by = $this->entityManager
                    ->find(User::class, $executedById);

        $members = $this->organizationService->findOrganizationMemberships(
            $organization, null, null,
            [
                OrganizationMembership::ROLE_ADMIN,
                OrganizationMembership::ROLE_MEMBER
            ]
        );

        $this->sendTaskRevertedToOpenInfoMail($task, $members, $by);
    }

    public function sendTaskRevertedToOpenInfoMail($task, array $recipients, User $by)
    {
        $subject = "Item \"{$task->getSubject()}\" has been reverted back to the 'open' state";
        $rv = [];

        foreach ($recipients as $recipient) {

            $message = $this->mailService->getMessage();
            $message->setTo($recipient->getMember()->getEmail());
            $message->setSubject("Item '$subject' was reverted back to Open state");

            $this->mailService->setTemplate( 'mail/task-reverted-to-open-info.phtml', [
                'task' => $task,
                'recipient' => $recipient->getMember(),
                'subject' => $subject,
                'by' => $by,
                'router' => $this->feRouter
            ]);

            $this->mailService->send();

            $rv[] = $recipient;
        }

        return $rv;
    }

}