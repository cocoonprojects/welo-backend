<?php

namespace People\Service;

use Doctrine\ORM\EntityManager;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Aggregate\AggregateRepository;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Stream\SingleStreamStrategy;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Rhumsaa\Uuid\Uuid;
use Application\Entity\User;
use People\Organization;
use People\Entity\Organization as ReadModelOrg;
use People\Entity\OrganizationMembership;
use People\Entity\OrganizationMemberContribution;

class EventSourcingOrganizationService extends AggregateRepository implements OrganizationService
{
	/**
	 *
	 * @var EntityManager
	 */
	protected $entityManager;

	public function __construct(EventStore $eventStore, EntityManager $entityManager)
	{
		parent::__construct($eventStore, new AggregateTranslator(), new SingleStreamStrategy($eventStore), AggregateType::fromAggregateRootClass(Organization::class));
		$this->entityManager = $entityManager;
	}

	/**
	 * @param string $name
	 * @param User $createdBy
	 * @return Organization
	 * @throws \Exception
	 */
	public function createOrganization($name, User $createdBy) {
		$this->eventStore->beginTransaction();
		try {
			$org = Organization::create($name, $createdBy);
			$this->addAggregateRoot($org);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $org;
	}

	public function updateOrganizationLanes($id, $lanes, User $updatedBy) {
		$this->eventStore->beginTransaction();
		try {
			$org = $this->getOrganization($id);
            $org->setLanes($lanes);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $org;
    }

	/**
	 * @param string|Uuid $id
	 * @return null|object
	 */
	public function getOrganization($id) {
		$oId = $id instanceof Uuid ? $id->toString() : $id;
		$rv = $this->getAggregateRoot($oId);
		return $rv;
	}

	/**
	 * @param string $id
	 * @return null|object
	 * @throws \Doctrine\ORM\ORMException
	 * @throws \Doctrine\ORM\OptimisticLockException
	 * @throws \Doctrine\ORM\TransactionRequiredException
	 */
	public function findOrganization($id)
	{
		$rv = $this->entityManager->find(ReadModelOrg::class, $id);
		return $rv;
	}

	/**
	 * @param User $user
	 * @return array
	 */
	public function findUserOrganizationMemberships(User $user)
	{
		$rv = $this->entityManager->getRepository(OrganizationMembership::class)->findBy(['member' => $user], ['createdAt' => 'ASC']);
		return $rv;
	}

	/**
	 * @param ReadModelOrg $organization
	 * @param integer $offset
	 * @param integer $limit
	 * @return array
	 */
	public function findOrganizationMemberships(ReadModelOrg $organization, $limit, $offset, $roles=[])
	{
		$criteria = ['organization' => $organization];
		// diff contains elements not available in the second array, so not good
		$diff = array_diff($roles, [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER, OrganizationMembership::ROLE_CONTRIBUTOR]);

		if (!empty($roles) && empty($diff)) {
			$criteria['role'] = $roles;
		}

		$rv = $this->entityManager->getRepository(OrganizationMembership::class)->findBy($criteria, ['createdAt' => 'ASC'], $limit, $offset);

		return $rv;

	}

    /**
     * Returns all admins for a given organization
     */
	public function findOrganizationAdmins($orgId)
    {
        $org = $this->findOrganization($orgId);

        if ($org === null) {
            return [];
        }

        $orgMembers = $this->findOrganizationMemberships(
            $org,
            null,
            null,
            [ OrganizationMembership::ROLE_ADMIN ]
        );

        return array_map(function($item) { return $item->getMember(); }, $orgMembers);
    }

	/**
	 * @param ReadModelOrg $organization
	 * @return integer
	 */
	public function countOrganizationMemberships(ReadModelOrg $organization, $roles=[]){
		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('count(om.member)')
			->from(OrganizationMembership::class, 'om')
			->where('om.organization = :organization')
			->setParameter(':organization', $organization)
			;

		// diff contains elements not available in the second array, so not good
		$diff = array_diff($roles, [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER, OrganizationMembership::ROLE_CONTRIBUTOR]);
		if (!empty($roles) && empty($diff)) {
			$query
				->andWhere('om.role in (:roles)')
				->setParameter(':roles', $roles);
		}
		return intval($query->getQuery()->getSingleScalarResult());
	}
	
	/**
	 * @return array
	 */
	public function findOrganizations()
	{
		$rv = $this->entityManager->getRepository(ReadModelOrg::class)->findBy([], ['name' => 'ASC']);
		return $rv;
	}

	public function getMemberContributionWithinDays($userId, $orgId, $numDays)
    {
        $date = (new \DateTime('now'))->sub(new \DateInterval("P{$numDays}D"));
        $entity = OrganizationMemberContribution::class;

        $sql = "SELECT COUNT(c.taskId) as numItemWorked, SUM(c.credits) as gainedCredits  
                FROM $entity c
                WHERE c.userId = :userId
                AND c.organizationId = :orgId
                AND c.occurredOn >= :date
                GROUP BY c.userId";

        $query = $this->entityManager->createQuery($sql);
        $query->setParameter(':userId', $userId);
        $query->setParameter(':orgId', $orgId);
        $query->setParameter(':date', $date);

        $contribution = $query->getArrayResult();

        if (empty($contribution)) {
            $contribution = [['numItemWorked' => 0, 'gainedCredits' => 0]];
        }

        return $contribution[0];
    }

	public function isMemberOverShiftOutQuota($userId, $orgId, $minCredits, $minItems, $withinDays)
    {
        $contribution = $this->getMemberContributionWithinDays($userId, $orgId, $withinDays);

        if ($contribution['numItemWorked'] < $minItems) {
            return false;
        }

        if ($contribution['gainedCredits'] < $minCredits) {
            return false;
        }

        return true;
    }

    public function updateMemberContribution($orgId, $userId, $taskId, $credit, $occurredOn)
    {
        $criteria = [
            'organizationId' => $orgId,
            'userId' => $userId,
            'taskId' => $taskId
        ];

        $contribution = $this->entityManager
             ->getRepository(OrganizationMemberContribution::class)
             ->findOneBy($criteria);

        if ($contribution === null) {

            $contribution = new OrganizationMemberContribution($orgId, $userId, $taskId, $credit, $occurredOn);

            $this->entityManager->persist($contribution);

            return;
        }

        $contribution->update($credit, $occurredOn);
        $this->entityManager->persist($contribution);
    }
}
