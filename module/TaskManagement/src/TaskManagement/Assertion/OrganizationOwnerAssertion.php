<?php

namespace TaskManagement\Assertion;

use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Assertion\AssertionInterface;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use Zend\Permissions\Acl\Role\RoleInterface;
use TaskManagement\Entity\Task;

class OrganizationOwnerAssertion implements AssertionInterface
{
    public function assert(Acl $acl, RoleInterface $user = null, ResourceInterface $resource = null, $privilege = null)
    {
        return $user->isOwnerOf($resource->getOrganizationId());
    }
}