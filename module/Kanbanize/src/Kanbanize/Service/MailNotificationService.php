<?php

namespace Kanbanize\Service;

use People\Entity\Organization;
use People\Service\OrganizationService;
use AcMailer\Service\MailServiceInterface;
use People\Entity\OrganizationMembership;
use TaskManagement\Service\TaskService;

class MailNotificationService implements NotificationService{

	public function __construct(MailServiceInterface $mailService, OrganizationService $orgService, TaskService $taskService) {
		$this->mailService = $mailService;
		$this->orgService = $orgService;
		$this->taskService = $taskService;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Kanbanize\Service\NotificationService::sendKanbanizeImportResult()
	 */
	public function sendKanbanizeImportResult($result, Organization $organization){
		$memberships = $this->orgService->findOrganizationMemberships($organization, null, null);
		foreach ($memberships as $m) {
			$recipient = $m->getMember();
			$message = $this->mailService->getMessage();
			$message->setTo($recipient->getEmail());
			$message->setSubject("A new import from Kanbanize as been completed.");
			$this->mailService->setTemplate( 'mail/import-result.phtml', [
					'result' => $result,
					'recipient'=> $recipient,
					'organization'=> $organization
			]);
			$this->mailService->send();
			$rv[] = $recipient;
		}
		return $rv;
	}

	public function sendKanbanizeSyncAlert(Organization $org)
	{
		$adminsMembers = $this->orgService
			 		   		  ->findOrganizationMemberships(
					 		   		$org,
					 		   		null,
					 		   		null,
			 				   		[OrganizationMembership::ROLE_ADMIN]);

		foreach ($adminsMembers as $adminsMember)
		{
			$admin = $adminsMember->getMember();

			$message = $this->mailService->getMessage();
			$message->setTo($admin->getEmail());
$message->setTo('marco.radossi@ideato.info');
			$message->setSubject("Your connected Kanbanize board is out of sync");

			$this->mailService->setTemplate('mail/board-out-of-sync.phtml', [
					'recipient'=> $admin,
					'organization'=> $org
			]);

			$this->mailService->send();
		}
	}

	public function sendKanbanizeSyncErrors(Organization $org, $orgErrors, $tasksErrors)
	{
		$adminsMembers = $this->orgService
			 		   		  ->findOrganizationMemberships(
					 		   		$org,
					 		   		null,
					 		   		null,
			 				   		[OrganizationMembership::ROLE_ADMIN]);

		foreach ($adminsMembers as $adminsMember)
		{
			$admin = $adminsMember->getMember();

			$message = $this->mailService->getMessage();
			$message->setTo($admin->getEmail());
$message->setTo('marco.radossi@ideato.info');
			$message->setSubject("Your connected Kanbanize board generated some error");

			$this->mailService->setTemplate('mail/sync-org-errors.phtml', [
					'recipient' => $admin,
					'organization' => $org,
					'errors' => $orgErrors,
			]);
			$this->mailService->send();
		}

        foreach ($tasksErrors as $taskId => $errors) {

		    $task = $this->taskService->findTask($taskId);
            $owner = $task->getOwner();
            if (!$owner) {
		        continue;
            }

            $message = $this->mailService->getMessage();
            $message->setTo($owner->getEmail());
$message->setTo('marco.radossi@ideato.info');
            $message->setSubject("A workitem you are involved in generated some error");

			$this->mailService->setTemplate('mail/sync-tasks-errors.phtml', [
					'recipient' => $owner,
					'organization' => $org,
					'errors' => $tasksErrors
			]);
        }
	}
}