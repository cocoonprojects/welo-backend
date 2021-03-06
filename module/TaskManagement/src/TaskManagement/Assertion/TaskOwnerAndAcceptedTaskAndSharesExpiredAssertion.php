<?php

namespace TaskManagement\Assertion;

use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use Zend\Permissions\Acl\Role\RoleInterface;
use TaskManagement\Entity\Task;

class TaskOwnerAndAcceptedTaskAndSharesExpiredAssertion extends ItemOwnerAssertion{
	public function assert(Acl $acl, RoleInterface $user = null, ResourceInterface $resource = null, $privilege = null)
	{
		return parent::assert($acl, $user, $resource, $privilege) &&
				$resource->getStatus() == Task::STATUS_ACCEPTED &&
				($resource->isSharesAssignmentCompleted() || $resource->isSharesAssignmentExpired(new \DateTime()));
	}
}