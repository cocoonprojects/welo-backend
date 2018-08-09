<?php

namespace TaskManagement\Service;

use AcMailer\Service\MailServiceInterface;
use Application\Entity\BasicUser;
use Application\Entity\User;
use Application\Service\FrontendRouter;
use Application\Service\UserService;
use People\Entity\Organization;
use People\Entity\OrganizationMembership;
use People\Service\OrganizationService;
use People\OrganizationMemberAdded;
use TaskManagement\Entity\Task;
use TaskManagement\EstimationAdded;
use TaskManagement\Event\TaskMemberRemoved;
use TaskManagement\SharesAssigned;
use TaskManagement\SharesSkipped;
use TaskManagement\TaskClosed;
use TaskManagement\TaskClosedByTimebox;
use TaskManagement\TaskDeleted;
use TaskManagement\TaskMemberAdded;
use TaskManagement\TaskNotClosedByTimebox;
use TaskManagement\TaskCreated;
use TaskManagement\TaskAccepted;
use TaskManagement\TaskReopened;
use TaskManagement\WorkItemIdeaCreated;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use TaskManagement\TaskOpened;
use TaskManagement\TaskArchived;
use TaskManagement\OwnerChanged;

class NotifyMailListener implements NotificationService, ListenerAggregateInterface
{
	/**
	 * @var MailServiceInterface
	 */
	private $mailService;
	/**
	 * @var UserService
	 */
	private $userService;
	/**
	 * @var TaskService
	 */
	private $taskService;
	/**
	 * @var OrganizationService
	 */
	private $orgService;
	/**
	 * @var array
	 */
	protected $listeners = [];
	/**
	 * @var string
	 */
	protected $host;

	protected $feRouter;

	public function __construct(
	    MailServiceInterface $mailService,
        UserService $userService,
        TaskService $taskService,
        OrganizationService $orgService,
        FrontendRouter $feRouter) {

	    $this->mailService = $mailService;
		$this->userService = $userService;
		$this->taskService = $taskService;
		$this->orgService = $orgService;
        $this->feRouter = $feRouter;
	}
	
	public function attach(EventManagerInterface $events) {
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, EstimationAdded::class, array($this, 'processEstimationAdded'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, SharesAssigned::class, array($this, 'processSharesAssigned'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, SharesSkipped::class, array($this, 'processSharesAssigned'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskClosed::class, array($this, 'processTaskClosed'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskClosedByTimebox::class, array($this, 'processTaskClosedByTimebox'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskNotClosedByTimebox::class, array($this, 'processTaskNotClosedByTimebox'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskCreated::class, array($this, 'processWorkItemIdeaCreated'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskAccepted::class, array($this, 'processTaskAccepted'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskOpened::class, array($this, 'processTaskOpened'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskArchived::class, array($this, 'processTaskArchived'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskDeleted::class, array($this, 'processTaskDeleted'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskMemberAdded::class, array($this, 'processTaskMemberAdded'));
		$this->listeners [] = $events->getSharedManager ()->attach (Application::class, TaskMemberRemoved::class, array($this, 'processTaskMemberRemoved'));

	}
	
	public function detach(EventManagerInterface $events) {
		foreach ( $this->listeners as $index => $listener ) {
			if ($events->detach ( $listener )) {
				unset ( $this->listeners [$index] );
			}
		}
	}

	public function processEstimationAdded(Event $event) {
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$memberId = $event->getParam ( 'by' );
		$member = $this->userService->findUser($memberId);
		$this->sendEstimationAddedInfoMail($task, $member);
	}

	public function processSharesAssigned(Event $event) {
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$memberId = $event->getParam ( 'by' );
		$member = $this->userService->findUser($memberId);
		$this->sendSharesAssignedInfoMail($task, $member);
	}

	public function processTaskClosed(Event $event){
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$this->sendTaskClosedInfoMail($task);
	}

	public function processTaskClosedByTimebox(Event $event){
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$this->sendTaskClosedByTimeboxInfoMail($task);
	}

	public function processTaskNotClosedByTimebox(Event $event){
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$this->sendTaskNotClosedByTimeboxInfoMail($task);
	}

	public function processWorkItemIdeaCreated(Event $event) {
		$streamEvent = $event->getTarget ();
		$taskId = $streamEvent->metadata ()['aggregate_id'];
		$task = $this->taskService->findTask ( $taskId );

		if ($task->getStatus() == Task::STATUS_IDEA) {
			$memberId = $event->getParam ( 'by' );
			$member = is_null($task->getMember($memberId)) ? null : $task->getMember($memberId)->getUser();
			$org = $task->getStream()->getOrganization();
			$memberships = $this->orgService->findOrganizationMemberships($org,null,null);

			$this->sendWorkItemIdeaCreatedMail ( $task, $member, $memberships);
		}
	}

	public function processTaskAccepted(Event $event){
		$streamEvent = $event->getTarget();
		$taskId = $streamEvent->metadata()['aggregate_id'];
		$task = $this->taskService->findTask($taskId);
		$this->sendTaskAcceptedInfoMail($task);
		$this->sendTaskAcceptedInfoMailToOrgUsers($task);
	}


	public function processTaskOpened(Event $event){
		$streamEvent = $event->getTarget ();
		$taskId = $streamEvent->metadata ()['aggregate_id'];
		$task = $this->taskService->findTask ( $taskId );
		
        $memberId = $event->getParam ( 'by' );
        $org = $task->getStream()->getOrganization();
        $memberships = $this->orgService->findOrganizationMemberships($org,null,null);
        $this->sendTaskOpenedInfoMail($task, $memberships);
		
	}
	
	public function processTaskArchived(Event $event){
		$streamEvent = $event->getTarget ();
		$taskId = $streamEvent->metadata ()['aggregate_id'];
		$task = $this->taskService->findTask ( $taskId );
	
		$org = $task->getStream()->getOrganization();
		$memberships = $this->orgService->findOrganizationMemberships($org,null,null);
		$this->sendTaskArchivedInfoMail($task, $memberships);
	
	}

	public function processTaskDeleted(Event $event) {

        $streamEvent = $event->getTarget();
        $payload = $streamEvent->payload();

        $userId = $event->getParam('by');
        $user = $this->userService->findUser($userId);

        $partecipants = $this->userService->findByIds(array_keys($payload['partecipants']));
        $admins = $this->orgService->findOrganizationAdmins($payload['organization']);

        $recipients = [];

        foreach ($partecipants as $partecipant) {
            $recipients[$partecipant->getId()] = $partecipant;
        }

        foreach ($admins as $admin) {
            $recipients[$admin->getId()] = $admin;
        }

        $this->sendTaskDeletedInfoMail($payload['subject'], $recipients, $user);
    }

    public function processTaskMemberAdded(Event $event)
    {
        $streamEvent = $event->getTarget();
        $payload = $streamEvent->payload();

        $taskId = $streamEvent->metadata()['aggregate_id'];
        $task = $this->taskService->findTask($taskId);
        $recipient = $task->getOwner();

        if (!$recipient) {
            return;
        }

        $userId = $event->getParam('userId');
        $user = $this->userService->findUser($userId);

        $this->sendTaskMemberAddedInfoMail($recipient->getUser(), $task, $user);

    }

    public function processTaskMemberRemoved(TaskMemberRemoved $event)
    {
        $taskId = $event->aggregateId();
        $task = $this->taskService->findTask($taskId);
        $recipient = $task->getOwner();

        if (!$recipient) {
            return;
        }

        $userId = $event->userId();
        $user = $this->userService->findUser($userId);

        $this->sendTaskMemberRemovedInfoMail($recipient->getUser(), $task, $user);

    }

    public function sendTaskDeletedInfoMail($subject, array $recipients, User $by)
    {
        $rv = [];

        foreach ($recipients as $recipent) {

            $message = $this->mailService->getMessage();
            $message->setTo($recipent->getEmail());
            $message->setSubject("Item '$subject' was deleted");

            $this->mailService->setTemplate( 'mail/task-deleted-info.phtml', [
                'recipient' => $recipent,
                'subject' => $subject,
                'by' => $by
            ]);

            $this->mailService->send();

            $rv[] = $recipent;
        }

        return $rv;
    }

	/**
	 * @param Task $task
	 * @param User $member
	 * @return BasicUser[] receivers
	 * @throws \AcMailer\Exception\MailException
	 */
	public function sendEstimationAddedInfoMail(Task $task, User $member)
	{
		$rv = [];
		if(!is_null($task->getOwner())){
			$owner = $task->getOwner()->getUser();
			
			//No mail to Owner for his actions
			if(strcmp($owner->getId(), $member->getId())==0){
				return $rv;
			}
			
			$message = $this->mailService->getMessage();
			$message->setTo($owner->getEmail());
			$message->setSubject('Estimation added to "' . $task->getSubject() . '" item');
			
			$this->mailService->setTemplate( 'mail/estimation-added-info.phtml', [
					'task' => $task,
					'recipient'=> $owner,
					'member'=> $member,
					'host' => $this->host,
                    'router' => $this->feRouter
			]);
			
			$this->mailService->send();
			$rv[] = $owner;
			return $rv;
		}
	}

	/**
	 * @param Task $task
	 * @param User $member
	 * @return BasicUser[] receivers
	 * @throws \AcMailer\Exception\MailException
	 */
	public function sendSharesAssignedInfoMail(Task $task, User $member)
	{
		$rv = [];
		if(!is_null($task->getOwner())){
			$owner = $task->getOwner()->getUser();
			
			//No mail to Owner for his actions
			if(strcmp($owner->getId(), $member->getId())==0){
				return $rv;
			}
			
			$message = $this->mailService->getMessage();
			$message->setTo($owner->getEmail());
			$message->setSubject('Shares assigned to "' . $task->getSubject() . '" item' );
			
			$this->mailService->setTemplate( 'mail/shares-assigned-info.phtml', [
					'task' => $task,
					'recipient'=> $owner,
					'member'=> $member,
					'host' => $this->host,
                    'router' => $this->feRouter
            ]);
			
			$this->mailService->send();
			$rv[] = $owner;
			return $rv;
		}
	}
	
	/**
	 * Send email notification to all members with empty shares of $taskToNotify
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function remindAssignmentOfShares(Task $task)
	{
		$rv = [];
		$taskMembersWithEmptyShares = $task->findMembersWithEmptyShares();

		foreach ($taskMembersWithEmptyShares as $tm){
			$member = $tm->getUser();
			$message = $this->mailService->getMessage();
			$message->setTo($member->getEmail());
			$message->setSubject('Assign your shares to "' . $task->getSubject() . '" item');

			$this->mailService->setTemplate( 'mail/reminder-assignment-shares.phtml', [
				'task' => $task,
				'recipient'=> $member,
				'host' => $this->host,
                'router' => $this->feRouter
			]);

			$this->mailService->send();
			$rv[] = $member;
		}
		return $rv;
	}
	
	/**
	 * Send email notification to all members with no estimation of $taskToNotify
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function remindEstimation(Task $task)
	{
		$rv = [];
		$taskMembersWithNoEstimation = $task->findMembersWithNoEstimation();
		foreach ($taskMembersWithNoEstimation as $tm){
			$member = $tm->getUser();
			$message = $this->mailService->getMessage();
			$message->setTo($member->getEmail());
			$message->setSubject('Estimate "'.$task->getSubject().'" item');
			
			$this->mailService->setTemplate( 'mail/reminder-add-estimation.phtml', [
				'task' => $task,
				'recipient'=> $member,
				'host' => $this->host,
                'router' => $this->feRouter
            ]);
			
			$this->mailService->send();
			$rv[] = $member;
		}
		return $rv;
	}
	
	/**
	 * Send an email notification to the members of $taskToNotify to inform them that it has been closed
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function sendTaskClosedInfoMail(Task $task)
	{
        $rv = [];

		$sharesSummary = $task->getSharesSummary();
		$avgCredits = $task->getAverageEstimation();

		$taskMembers = $task->getMembers();
        $sharesSummary = array_map(function ($share) {
            $share['share'] = $this->formatFloatForOutput($share['share'], 1);
            $share['value'] = !is_null($share['value']) ? $this->formatFloatForOutput($share['value'], 1) : 'n/a';

            $share['gap'] = ($share['gap'] !== 'n/a') ? $this->formatFloatForOutput($share['gap'], 1) : 'n/a';
            return $share;
        }, $sharesSummary);

		foreach ($taskMembers as $taskMember) {
            $member = $taskMember->getMember();

            $message = $this->mailService->getMessage();
            $message->setTo($member->getEmail());
            $message->setSubject('The "' . $task->getSubject() . '" item has been closed');

            $this->mailService->setTemplate( 'mail/task-closed-info.phtml', [
				'task' => $task,
				'recipient'=> $member,
				'host' => $this->host,
                'sharesSummary' => $sharesSummary,
                'avgCredits' => $this->formatFloatForOutput($avgCredits, 1),
                'router' => $this->feRouter
            ]);

			$this->mailService->send();

			$rv[] = $member;
		}
		return $rv;
	}

	/**
	 * Send an email notification to the owner of $taskToNotify to inform that it has been closed
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function sendTaskClosedByTimeboxInfoMail(Task $task)
	{
		$rv = [];

        $owner = $task->getOwner()->getMember();

        $message = $this->mailService->getMessage();
        $message->setTo($owner->getEmail());
        $message->setSubject('The "'.$task->getSubject().'" item has been automatically closed');

        $this->mailService->setTemplate( 'mail/task-closed-timebox-info.phtml', [
            'task' => $task,
            'recipient'=> $owner,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);

        $this->mailService->send();
        $rv[] = $owner;

        return $rv;
	}

	/**
	 * Send an email notification to the owner of $taskToNotify to inform that it has not been closed
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function sendTaskNotClosedByTimeboxInfoMail(Task $task)
	{
		$rv = [];

        $owner = $task->getOwner()->getMember();

        $message = $this->mailService->getMessage();
        $message->setTo($owner->getEmail());
        $message->setSubject('The "'.$task->getSubject().'" item cannot be automatically closed');

        $this->mailService->setTemplate( 'mail/task-not-closed-timebox-info.phtml', [
            'task' => $task,
            'recipient'=> $owner,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);

        $this->mailService->send();
        $rv[] = $owner;

		return $rv;
	}

	/**
	 * Send an email notification to the organization members to inform them that a new Work Item Idea has been created
	 * @param Task $task
	 * @param User | null $member
	 * @param OrganizationMembership[] $memberships
	 * @return BasicUser[] receivers
	 */
	public function sendWorkItemIdeaCreatedMail(Task $task, $member, $memberships){
		$rv = [];
		$org = $task->getStream()->getOrganization();
		$stream = $task->getStream();

        $authorId = $task->getAuthor()->getId();

        foreach ($memberships as $m) {
			$recipient = $m->getMember();

			if ($authorId == $recipient->getId()) {
			    // do not send creation notification to the creator
                continue;
            }

			$message = $this->mailService->getMessage();
			$message->setTo($recipient->getEmail());
			$message->setSubject('A new idea proposed into the "' . $stream->getSubject() . '" stream');
			
			$this->mailService->setTemplate( 'mail/work-item-idea-created.phtml', [
				'task' => $task,
				'member' => $member,
				'recipient'=> $recipient,
				'isRecipientContributor' => $m->isContributor(),
				'organization'=> $org,
				'stream'=> $stream,
				'host' => $this->host,
                'router' => $this->feRouter
			]);
			$this->mailService->send();
			$rv[] = $recipient;
		}
		return $rv;
	}

	/**
	 * Send an email notification to the members of $taskToNotify to inform them that it has been accepted, and it's time to assign shares
	 * @param Task $task
	 * @return BasicUser[] receivers
	 */
	public function sendTaskAcceptedInfoMail(Task $task)
	{
		$rv = [];
		$taskMembers = $task->getMembers();
		foreach ($taskMembers as $taskMember) {
			$member = $taskMember->getMember();
	
			$message = $this->mailService->getMessage();
			$message->setTo($member->getEmail());
			$message->setSubject('The "'.$task->getSubject().'" item has been accepted');
	
			$this->mailService->setTemplate( 'mail/task-accepted-info.phtml', [
				'task' => $task,
				'recipient'=> $member,
				'host' => $this->host,
                'router' => $this->feRouter
            ]);
				
			$this->mailService->send();
			$rv[] = $member;
		}

		return $rv;
	}


    /**
     * Send an email notification to the members of $taskToNotify to inform them that it has been accepted, and it's time to assign shares
     * @param Task $task
     * @return BasicUser[] receivers
     */
    public function sendTaskAcceptedInfoMailToOrgUsers(Task $task)
    {
        $rv = [];
        $taskMembers = $task->getMembers();
        $taskMemberEmails = array_map(function ($member) {
            return $member->getMember()->getEmail();
        }, $taskMembers);

        $organization = $task->getStream()->getOrganization();
        $organizationUsers = $this->orgService->findActiveOrganizationMemberships($organization, 9999999, 0);
        $organizationUsers = array_filter($organizationUsers, function ($organizationUser) use ($taskMemberEmails) {
            return !in_array($organizationUser->getMember()->getEmail(), $taskMemberEmails);
        });

        foreach ($organizationUsers as $orgUser) {
            $member = $orgUser->getMember();

            $message = $this->mailService->getMessage();
            $message->setTo($member->getEmail());
            $message->setSubject('The "' . $task->getSubject() . '" item has been accepted');

            $this->mailService->setTemplate('mail/task-accepted-for-org-users-info.phtml', [
                'task' => $task,
                'recipient' => $member,
                'host' => $this->host,
                'router' => $this->feRouter
            ]);

            $this->mailService->send();
            $rv[] = $member;
        }

        return $rv;
    }


	public function sendTaskOpenedInfoMail(Task $task, $memberships)
	{
		$rv = [];
		$org = $task->getStream()->getOrganization();
		$stream = $task->getStream();
		
		foreach ($memberships as $m) {
			$recipient = $m->getMember();
				
			$message = $this->mailService->getMessage();
			$message->setTo($recipient->getEmail());
			$message->setSubject('A new idea has been accepted in "' . $stream->getSubject() . '" stream');
				
			$this->mailService->setTemplate( 'mail/task-opened-info.phtml', [
					'task' => $task,
					'recipient'=> $recipient,
					'organization'=> $org,
					'stream'=> $stream,
					'host' => $this->host,
                    'router' => $this->feRouter
            ]);
			$this->mailService->send();
			$rv[] = $recipient;
		}
		return $rv;
		
	}
	
	public function sendTaskArchivedInfoMail(Task $task, $memberships)
	{
		$rv = [];
		$org = $task->getStream()->getOrganization();
		$stream = $task->getStream();
	
		foreach ($memberships as $m) {
			$recipient = $m->getMember();
	
			$message = $this->mailService->getMessage();
			$message->setTo($recipient->getEmail());
			$message->setSubject('A new idea has been rejected in "' . $stream->getSubject() . '" stream');
	
			$this->mailService->setTemplate( 'mail/task-archived-info.phtml', [
					'task' => $task,
					'recipient'=> $recipient,
					'organization'=> $org,
					'stream'=> $stream,
					'host' => $this->host,
                   'router' => $this->feRouter
            ]);
			$this->mailService->send();
			$rv[] = $recipient;
		}
		return $rv;
	
	}

	public function sendTaskMemberAddedInfoMail(BasicUser $recipient, Task $task, BasicUser $user)
    {
        $org = $task->getStream()->getOrganization();
        $stream = $task->getStream();

        $message = $this->mailService->getMessage();
        $message->setTo($recipient->getEmail());
        $message->setSubject('A new user is taking part in "' . $task->getSubject() . '"');

        $this->mailService->setTemplate( 'mail/task-member-added-info.phtml', [
            'task' => $task,
            'user' => $user,
            'recipient'=> $recipient,
            'organization'=> $org,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);
        $this->mailService->send();

        return [$recipient];
    }

	public function sendTaskMemberRemovedInfoMail(BasicUser $recipient, Task $task, BasicUser $user)
    {
        $org = $task->getStream()->getOrganization();
        $stream = $task->getStream();

        $message = $this->mailService->getMessage();
        $message->setTo($recipient->getEmail());
        $message->setSubject('A user is no longer taking part in "' . $task->getSubject() . '"');

        $this->mailService->setTemplate( 'mail/task-member-removed-info.phtml', [
            'task' => $task,
            'user' => $user,
            'recipient'=> $recipient,
            'organization'=> $org,
            'host' => $this->host,
            'router' => $this->feRouter
        ]);
        $this->mailService->send();

        return [$recipient];
    }


    /**
	 * @return MailServiceInterface
	 */
	public function getMailService() {
		return $this->mailService;
	}

	public function getOrganizationService(){
		return $this->orgService;
	}

	public function setHost($host) {
		$this->host = $host;
		return $this;
	}

    private function formatFloatForOutput($number, $decimals) {
	    $value = round(floatval($number), $decimals);
	    if ($value == floor($value)) {
	        $value = round(floatval($number), $decimals-1);
        }
        return $value;
    }

}
