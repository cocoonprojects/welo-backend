<?php

namespace TaskManagement\Assertion;

use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use Zend\Permissions\Acl\Role\RoleInterface;
use Zend\Permissions\Acl\Assertion\AssertionInterface;
use TaskManagement\Entity\Task;

class AdminOrItemAuthorOrItemOwnerAssertion implements AssertionInterface{
	
	public function assert(Acl $acl, RoleInterface $user = null, ResourceInterface $resource = null, $privilege = null)
	{
	    $isOwner = $resource->getMemberRole($user) === Task::ROLE_OWNER;
	    $isAuthor = $resource->isAuthor($user) && in_array($resource->getStatus(), [Task::STATUS_IDEA, Task::STATUS_OPEN]);
        $isAdmin = $user->isOwnerOf($resource->getOrganizationId());

		return $isAdmin || $isAuthor || $isOwner;
	}
}