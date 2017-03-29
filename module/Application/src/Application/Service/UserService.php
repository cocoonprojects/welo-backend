<?php 
namespace Application\Service;

interface UserService
{
	/**
	 * User Subscribe to the system the first time user log in with supported SSO
	 *
	 * @param array [email, family_name, given_name, picture]
	 * @return User
	 */	
	public function subscribeUser($userInfo);

	/**
	 * Find a User by id
	 *
	 * @param mixed $id
	 * @return User
	 */	
	public function findUser($id);
	
	/**
	 * Find a User by Email
	 *
	 * @param string $email
	 * @return User
	 */
	public function findUserByEmail($email);

	/**
	 * Find users by $filters
	 * @param String $filters["kanbanizeUsername"]
	 * @return User[]
	 */
	public function findUsers($filters);
}
