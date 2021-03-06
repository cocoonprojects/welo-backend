<?php
namespace Application\Service;

use Accounting\Assertion\AccountHolderAssertion;
use Accounting\Assertion\MemberOfAccountOrganizationAssertion;
use Application\Assertion\MemberOfEntityOrganizationAssertion;
use Application\Entity\User;
use People\Assertion\CommonOrganizationAssertion;
use People\Assertion\MemberOfOrganizationAssertion;
use TaskManagement\Assertion\AcceptedTaskAndMemberSharesNotAssignedAssertion;
use TaskManagement\Assertion\AdminOrItemAuthorOrItemOwnerAssertion;
use TaskManagement\Assertion\MemberOfOngoingTaskAssertion;
use TaskManagement\Assertion\OrganizationMemberNotTaskMemberAndNotCompletedTaskAssertion;
use TaskManagement\Assertion\OrganizationOwnerAssertion;
use TaskManagement\Assertion\OwnerOfWorkItemIdeaOrOpenOrCompletedTaskAssertion;
use TaskManagement\Assertion\TaskOwnerAndAcceptedTaskAndSharesExpiredAssertion;
use TaskManagement\Assertion\TaskMemberNotOwnerAndNotCompletedTaskAssertion;
use TaskManagement\Assertion\TaskOwnerAndCompletedTaskWithEstimationProcessCompletedAssertion;
use TaskManagement\Assertion\TaskOwnerAndOngoingOrAcceptedTaskAssertion;
use TaskManagement\Assertion\TaskOwnerAndOngoingTaskAssertion;
use TaskManagement\Assertion\WorkItemIdeaAndOrganizationMemberNotVotedAssertion;
use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use People\Assertion\OwnerOfOrganizationAssertion;

class AclFactory implements FactoryInterface
{
	public function createService(ServiceLocatorInterface $serviceLocator)
	{
		$acl = new Acl();
		$acl->addRole(User::ROLE_GUEST);
		$acl->addRole(User::ROLE_USER);
		$acl->addRole(User::ROLE_ADMIN, User::ROLE_USER);
		$acl->addRole(User::ROLE_SYSTEM);

		$acl->addResource('Ora\Organization');
		$acl->allow(User::ROLE_USER, 'Ora\Organization', [
			'People.Organization.userList',
			'TaskManagement.Task.list',
			'TaskManagement.Stream.list',
			'TaskManagement.Task.stats',
			'Accounting.Accounts.list',
			'Kanbanize.Settings.list',
			'Kanbanize.BoardSettings.get',
		], new MemberOfOrganizationAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\Organization', [
			'Kanbanize.Task.import',
			'Kanbanize.Settings.create',
			'Kanbanize.BoardSettings.create',
			'Kanbanize.BoardSettings.delete',
            'People.Organization.manageLanes',
		], new OwnerOfOrganizationAssertion());

		$acl->addResource('Ora\User');
		$acl->allow(User::ROLE_USER, 'Ora\User', 'People.Member.get', new CommonOrganizationAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\User', 'People.Member.update', new CommonOrganizationAssertion());

		$acl->addResource('Ora\PersonalAccount');
		$acl->addResource('Ora\OrganizationAccount');
		$acl->allow(User::ROLE_USER, 'Ora\PersonalAccount', 'Accounting.Account.get', new MemberOfEntityOrganizationAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\PersonalAccount','Accounting.Account.statement', new AccountHolderAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\OrganizationAccount','Accounting.Account.statement', new MemberOfAccountOrganizationAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\OrganizationAccount','Accounting.Account.deposit', new AccountHolderAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\OrganizationAccount','Accounting.Account.withdrawal', new AccountHolderAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\OrganizationAccount', 'Accounting.Account.incoming-transfer', new AccountHolderAssertion());
		$acl->allow(User::ROLE_USER, 'Ora\OrganizationAccount', 'Accounting.Account.outgoing-transfer', new AccountHolderAssertion());

		$acl->addResource('Ora\Task');
		$acl->addResource('Ora\KanbanizeTask');
		$acl->allow(User::ROLE_USER, null, 'TaskManagement.Task.create');
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.get', new MemberOfEntityOrganizationAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.join', new OrganizationMemberNotTaskMemberAndNotCompletedTaskAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.estimate', new MemberOfOngoingTaskAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.unjoin', new TaskMemberNotOwnerAndNotCompletedTaskAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.approve', new WorkItemIdeaAndOrganizationMemberNotVotedAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.edit', new AdminOrItemAuthorOrItemOwnerAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.delete', new OrganizationOwnerAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.execute', new OwnerOfWorkItemIdeaOrOpenOrCompletedTaskAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.complete', new TaskOwnerAndOngoingOrAcceptedTaskAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.accept', new TaskOwnerAndCompletedTaskWithEstimationProcessCompletedAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Task.assignShares', new AcceptedTaskAndMemberSharesNotAssignedAssertion());
		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'], 'TaskManagement.Reminder.add-estimation', new TaskOwnerAndOngoingTaskAssertion());

		$acl->allow(User::ROLE_USER, ['Ora\Task','Ora\KanbanizeTask'],'TaskManagement.Task.close', new TaskOwnerAndAcceptedTaskAndSharesExpiredAssertion());
		$acl->allow(User::ROLE_SYSTEM, null, [
				'TaskManagement.Task.closeTasksCollection',
				'TaskManagement.Reminder.assignment-of-shares',
				'TaskManagement.Reminder.approve',
				'TaskManagement.Task.close-voting-idea-items',
				'TaskManagement.Task.close-voting-completed-items',
				'TaskManagement.Approval.idea-items',
		]);
		return $acl;
	}
}
