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
	public function findOrganizationMemberships(ReadModelOrg $organization, $limit, $offset, $roles = [])
	{
        $builder = $this->entityManager->createQueryBuilder();

        $query = $builder->select('om')
            ->addSelect("(CASE WHEN om.role = 'admin' THEN 0 WHEN om.role = 'member' THEN 1 ELSE 2 END) AS HIDDEN ord")
            ->from(OrganizationMembership::class, 'om')
            ->leftJoin(User::class, 'u', 'WITH', 'om.member = u.id')
            ->where('om.organization = :organization')
            ->orderBy('ord', 'ASC')
            ->addOrderBy('u.firstname', 'ASC')
            ->addOrderBy('u.lastname', 'ASC')
            ->setParameter(':organization', $organization)
        ;

        $diff = array_diff($roles, [OrganizationMembership::ROLE_ADMIN, OrganizationMembership::ROLE_MEMBER, OrganizationMembership::ROLE_CONTRIBUTOR]);

        if (!empty($roles) && empty($diff)) {
            $query
                ->andWhere('om.role in (:roles)')
                ->setParameter(':roles', $roles);
        }

        return $query->getQuery()->getResult();
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

	public function isMemberOverShiftOutQuota($userId, $orgId, $minCredits, $minItems, $withinDays)
    {
        $date = (new \DateTime('now'))->sub(new \DateInterval("P{$withinDays}D"));
        $entity = OrganizationMemberContribution::class;


        $sql = "SELECT count(c.taskId)  
                FROM $entity c
                WHERE c.userId = :userId
                AND c.organizationId = :orgId
                AND c.occurredOn >= :occurredOn
                AND c.credits >= :minCredits";

        $query = $this->entityManager->createQuery($sql);
        $query->setParameter(':userId', $userId);
        $query->setParameter(':orgId', $orgId);
        $query->setParameter(':occurredOn', $date);
        $query->setParameter(':minCredits', $minCredits);

        $contribution = $query->getSingleScalarResult();

        return $contribution >= $minItems;
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

        //siamo nel caso di un revert
        if ($contribution && $credit < 0) {
            $this->entityManager->remove($contribution);

            return;
        }

        $contribution->update($credit, $occurredOn);
        $this->entityManager->persist($contribution);
    }
}
