<?php

namespace TaskManagement;


use Application\Entity\BasicUser;
use Zend\Permissions\Acl\Resource\ResourceInterface;

interface TaskInterface extends ResourceInterface
{
	const STATUS_IDEA      = 0;
	const STATUS_OPEN      = 10;
	const STATUS_ONGOING   = 20;
	const STATUS_COMPLETED = 30;
	const STATUS_ACCEPTED  = 40;
	const STATUS_CLOSED    = 50;
	const STATUS_DELETED   = -10;
	const STATUS_ARCHIVED  = -20;

	const ROLE_MEMBER = 'member';
	const ROLE_OWNER = 'owner';

	/**
	 * @return string
	 */
	public function getId();

	/**
	 * @return string
	 */
	public function getType();

	/**
	 * @return string
	 */
	public function getOrganizationId();

	/**
	 * @return string
	 */
	public function getSubject();

	/**
	 * @return string
	 */
	public function getDescription();

	/**
	 * @return \DateTime
	 */
	public function getCreatedAt();

	/**
	 * @return User
	 */
	public function getCreatedBy();

	/**
	 * @return \DateTime
	 */
	public function getAcceptedAt();

	/**
	 * @return int
	 */
	public function getStatus();

	/**
	 * @return string
	 */
	public function getStreamId();

	/**
	 * @return array
	 */
	public function getMembers();
	/**
	 * @return array
	 */
	public function getApprovals();
	/**
	 * @param id|BasicUser $user
	 * @return boolean
	 */
	public function hasMember($user);
}