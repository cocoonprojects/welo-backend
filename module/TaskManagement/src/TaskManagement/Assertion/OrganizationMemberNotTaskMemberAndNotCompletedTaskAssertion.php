<?php

namespace TaskManagement\Assertion;

use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use Zend\Permissions\Acl\Role\RoleInterface;
use TaskManagement\Entity\Task;

class OrganizationMemberNotTaskMemberAndNotCompletedTaskAssertion extends NotCompletedTaskAssertion
{
	public function assert(Acl $acl, RoleInterface $user = null, ResourceInterface $resource = null, $privilege = null)
	{
		return parent::assert($acl, $user, $resource, $privilege)
			&& $user->isMemberOf($resource->getOrganizationId())
			&& !$resource->hasMember($user) && $resource->getStatus() > Task::STATUS_IDEA ;
	}
}
