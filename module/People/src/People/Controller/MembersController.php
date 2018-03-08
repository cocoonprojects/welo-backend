<?php

namespace People\Controller;

use Application\Controller\OrganizationAwareController;
use Application\DomainEntityUnavailableException;
use Application\DuplicatedDomainEntityException;
use Application\Service\UserService;
use People\Entity\OrganizationMembership;
use People\Service\OrganizationService;
use TaskManagement\Service\TaskService;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\GreaterThan;
use Zend\Validator\ValidatorChain;
use Zend\View\Model\JsonModel;

class MembersController extends OrganizationAwareController
{
	protected static $collectionOptions = ['GET', 'DELETE', 'POST'];
	protected static $resourceOptions = ['GET', 'DELETE', 'PUT'];

	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var TaskService
	 */
	protected $taskService;

	public function __construct(
		OrganizationService $organizationService,
		UserService $userService,
        TaskService $taskService
    )
	{
		parent::__construct($organizationService);
		$this->userService = $userService;
		$this->taskService = $taskService;
	}

	public function getList()
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
        }

		if(!$this->isAllowed($this->identity(), $this->organization, 'People.Organization.userList')) {
			$this->response->setStatusCode(403);
			return $this->response;
		}

		$validator = new ValidatorChain();
		$validator->attach(new IsInt())
			->attach(new GreaterThan(['min' => 0, 'inclusive' => false]));

		$offset = $validator->isValid($this->getRequest()->getQuery("offset")) ? intval($this->getRequest()->getQuery("offset")) : 0;
		$limit = $validator->isValid($this->getRequest()->getQuery("limit")) ? intval($this->getRequest()->getQuery("limit")) : $this->organization->getParams()->get('org_members_limit_per_page');

		$memberships = $this->getOrganizationService()
                            ->findOrganizationMemberships($this->organization, $limit, $offset);

		$totalMemberships = $this->getOrganizationService()->countOrganizationMemberships($this->organization);

		$involvements = [];
        foreach ($memberships as $membership) {
            $memberId = $membership->getMember()->getId();
            $involvements[$memberId] = $this->taskService->findMemberInvolvement($this->organization, $memberId);
		}

		$hal = [
			'count' => count($memberships),
			'total' => $totalMemberships,
			'_embedded' => [
				'ora:member' => array_column(array_map([$this, 'serializeOne'], $memberships, $involvements), null, 'id')
			],
			'_links' => [
				'self' => [
					'href' => $this->url()->fromRoute('members', ['orgId' => $this->organization->getId()]),
				],
				'first' => [
					'href' => $this->url()->fromRoute('members', ['orgId' => $this->organization->getId()]),
				],
				'last' => [
					'href' => $this->url()->fromRoute('members', ['orgId' => $this->organization->getId()]),
				]
			]
		];
		if($hal['count'] < $hal['total']){
			$hal['_links']['next']['href'] = $this->url()->fromRoute('members', ['orgId' => $this->organization->getId()]);
		}
		return new JsonModel($hal);
	}

	public function create($data)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$organization = $this->getOrganizationService()->getOrganization($this->params('orgId'));
		if(is_null($organization)) {
			$this->response->setStatusCode(404);
			return $this->response;
		}

		$this->transaction()->begin();
		try {
			$organization->addMember($this->identity());
			$this->transaction()->commit();
			$this->response->setStatusCode(201);
		} catch (DuplicatedDomainEntityException $e) {
			$this->transaction()->rollback();
			$this->response->setStatusCode(204);
		}
		return $this->response;
	}

	public function delete($id)
	{
        if (is_null($this->identity()) || !$this->identity()->isOwnerOf($this->organization)) {
            $this->response->setStatusCode(401);
            return $this->response;
        }
        $memberToRemove = $this->identity();
        try {
            $memberId = $id;
            if (!empty($memberId)
                && ($member = $this->userService->findUser($memberId))
                && preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', $member->getId()) !== false
            ) {
                $memberToRemove = $member;
            }
        } catch (DomainEntityUnavailableException $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(204);    // No content = nothing changed
        }

        $organization = $this->getOrganizationService()->getOrganization($this->params('orgId'));
        $this->transaction()->begin();
        try {
            $organization->removeMember($memberToRemove);
            $this->transaction()->commit();
            $this->response->setStatusCode(200);
        } catch (DomainEntityUnavailableException $e) {
            $this->transaction()->rollback();
            $this->response->setStatusCode(204);
        }
        return $this->response;
	}

	public function deleteList($data)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$organization = $this->getOrganizationService()->getOrganization($this->params('orgId'));
		if(is_null($organization)) {
			$this->response->setStatusCode(404);
			return $this->response;
		}

		$this->transaction()->begin();
		try {
			$organization->removeMember($this->identity());
			$this->transaction()->commit();
			$this->response->setStatusCode(200);
		} catch (DomainEntityUnavailableException $e) {
			$this->transaction()->rollback();
			$this->response->setStatusCode(204);
		}
		return $this->response;
	}

	/**
	 * Return single resource
	 *
	 * @param  mixed $id
	 * @return mixed
	 */
	public function get($id)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$user = $this->userService->findUser($id);
		if(is_null($user)){
			$this->response->setStatusCode(404);
			return $this->response;
		}

		if(!$this->isAllowed($this->identity(), $user, 'People.Member.get')) {
			$this->response->setStatusCode(403);
			return $this->response;
		}

		$membership = $user->getMembership($this->organization);
		return is_null($membership) ? new JsonModel([new \stdClass()]) : new JsonModel($this->serializeOne($membership));
	}

	public function update($id, $data)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);
			return $this->response;
		}

		$user = $this->userService->findUser($id);
		if(is_null($user)){
			$this->response->setStatusCode(404);
			return $this->response;
		}

		if(!$this->isAllowed($this->identity(), $user, 'People.Member.update')) {
			$this->response->setStatusCode(403);
			return $this->response;
		}

		$membership = $user->getMembership($this->organization);
		if (!is_null($membership)) {

			$organization = $this->getOrganizationService()->getOrganization($this->params('orgId'));

			$admins = $organization->getAdmins();
			if ($membership->getRole()==OrganizationMembership::ROLE_ADMIN && count($admins)<2) {
				$this->response->setStatusCode(403);
				return $this->response;
			}

			$this->transaction()->begin();
			try {

			    if (array_key_exists('role', $data)) {
				    $organization->changeMemberRole($user, $data['role'], $this->identity());
                }

			    if (array_key_exists('active', $data)) {
				    $organization->changeMemberActivation($user, $data['active'], $this->identity());
                }

				$this->transaction()->commit();
				$this->response->setStatusCode(201);
			} catch (DuplicatedDomainEntityException $e) {
				$this->transaction()->rollback();
				$this->response->setStatusCode(204);
			}
		}

		return is_null($membership) ? new JsonModel([new \stdClass()]) : new JsonModel($this->serializeOne($membership));
	}

	/**
	 * @return UserService
	 */
	public function getUserService()
	{
		return $this->userService;
	}

	protected function getCollectionOptions()
	{
		return self::$collectionOptions;
	}

	protected function getResourceOptions()
	{
		return self::$resourceOptions;
	}

	protected function serializeOne(OrganizationMembership $membership, $involvements = null) {

		$member = [
			'id'        => $membership->getMember()->getId(),
			'firstname' => $membership->getMember()->getFirstname(),
			'lastname'  => $membership->getMember()->getLastname(),
			'email'     => $membership->getMember()->getEmail(),
			'secondaryEmails'     => $membership->getMember()->getSecondaryEmails(),
			'picture'   => $membership->getMember()->getPicture(),
			'role'      => $membership->getRole(),
			'active'      => $membership->getActive() ? true : false,
			'createdAt' => date_format($membership->getCreatedAt(), 'c'),
			'createdBy' => is_null ( $membership->getCreatedBy() ) ? "" : $membership->getCreatedBy ()->getFirstname () . " " . $membership->getCreatedBy ()->getLastname (),
			'_links' => [
				'self' => [
					'href' => $this->url()->fromRoute('members', ['orgId' => $membership->getOrganization()->getId(), 'id' => $membership->getMember()->getId()])
				],
				'ora:account' => [
					'href' => $this->url()->fromRoute('statements', ['orgId' => $membership->getOrganization()->getId(), 'id' => $membership->getMember()->getId(), 'controller' => 'members'])
				],
				'ora:member-stats' => [
					'href' => $this->url()->fromRoute('collaboration', ['orgId' => $membership->getOrganization()->getId(), 'id' => $membership->getMember()->getId(), 'controller' => 'member-stats'])
				]
			]
		];

		if (!is_null($involvements) && is_array($involvements)) {
		    $member['involvement'] = $involvements;
        }

		return $member;
	}

}
