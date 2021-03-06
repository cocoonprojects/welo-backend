<?php

namespace FlowManagement\Service;

use Doctrine\ORM\EntityManager;
use FlowManagement\Entity\FlowCard as ReadModelFlowCard;
use FlowManagement\FlowCard;
use Application\Entity\User;
use People\Entity\Organization;
use Rhumsaa\Uuid\Uuid;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Aggregate\AggregateRepository;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Stream\SingleStreamStrategy;
use Prooph\EventSourcing\EventStoreIntegration\AggregateTranslator;
use Application\Entity\BasicUser;
use FlowManagement\VoteIdeaCard;
use FlowManagement\VoteCompletedItemCard;
use FlowManagement\VoteCompletedItemVotingClosedCard;
use FlowManagement\VoteCompletedItemReopenedCard;
use FlowManagement\ItemOwnerChangedCard;
use FlowManagement\ItemMemberAddedCard;
use FlowManagement\ItemMemberRemovedCard;
use FlowManagement\OrganizationMemberRoleChangedCard;
use TaskManagement\Entity\Stream;
use TaskManagement\Entity\Task;

class EventSourcingFlowService extends AggregateRepository implements FlowService{

	/**
	 *
	 * @var EntityManager
	 */
	private $entityManager;

	public function __construct(EventStore $eventStore, EntityManager $entityManager) {
		parent::__construct($eventStore, new AggregateTranslator(), new SingleStreamStrategy($eventStore), AggregateType::fromAggregateRootClass(FlowCard::class));
		$this->entityManager = $entityManager;
	}

	public function getCard($id){
		$cId = $id instanceof Uuid ? $id->toString() : $id;
		$card = $this->getAggregateRoot($cId);
		return $card;
	}


    /**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::findFlowCards()
	 */
	public function findFlowCards(User $recipient, $offset, $limit, $filters = [])
    {
		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('f')
			->from(ReadModelFlowCard::class, 'f')
            ->where('f.recipient = :recipient')
            ->andWhere('f.hidden = false');

        if (is_array($filters)) {
            foreach ($filters as $param => $value) {
                $paramSlug = str_replace('.', '_', $param);
                $query
                    ->andWhere("$param = :$paramSlug")
                    ->setParameter(":$paramSlug", $value);
            }
        }

        $query
            ->groupBy('f.id')
            ->orderBy('f.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter(':recipient', $recipient);

		return $query->getQuery()->getResult();
	}


	public function findOrgFlowCards(User $recipient, $orgId, $offset, $limit, $filters = [])
    {
        $cards = $this->findFlowCards($recipient, null, null, $filters);
        $cards = array_filter($cards, function($card) use ($orgId) {

            $currentOrgId = null;

            $content = $card->getContent();
            $currentCardContent = array_shift($content);

            if (isset($currentCardContent['orgId']) && $currentCardContent['orgId']) {
                $currentOrgId = $currentCardContent['orgId'];
            }

            if (!$currentOrgId && $card->getItem()) {
                $currentOrgId = $card->getItem()->getStream()->getOrganization()->getId();
            }

            if (!$currentOrgId) {
                return false;
            }

            return ($orgId == $currentOrgId);
        });

		return array_slice($cards, $offset, $limit);
	}


	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createLazyMajorityVoteCard()
	 */
	public function createVoteIdeaCard(BasicUser $recipient, $itemId, $organizationid, BasicUser $createdBy){
		$content = [
				"orgId" => $organizationid
		];
		$this->eventStore->beginTransaction();
		try {
			$card = VoteIdeaCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}

	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createLazyMajorityVoteCard()
	 */
	public function createVoteCompletedItemCard(BasicUser $recipient, $itemId, $organizationid, BasicUser $createdBy){
		$content = [
				"orgId" => $organizationid
		];
		$this->eventStore->beginTransaction();
		try {
			$card = VoteCompletedItemCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}

	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createLazyMajorityVoteCard()
	 */
	public function createVoteCompletedItemVotingClosedCard(BasicUser $recipient, $itemId, $organizationid, BasicUser $createdBy){
		$content = [
				"orgId" => $organizationid
		];
		$this->eventStore->beginTransaction();
		try {
			$card = VoteCompletedItemVotingClosedCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}
	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createVoteCompletedItemReopenedCard()
	 */
	public function createVoteCompletedItemReopenedCard(BasicUser $recipient, $itemId, $organizationid, BasicUser $createdBy){
		$content = [
				"orgId" => $organizationid
		];
		$this->eventStore->beginTransaction();
		try {
			$card = VoteCompletedItemReopenedCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}
	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createItemOwnerChangedCard()
	 */
	public function createItemOwnerChangedCard(BasicUser $recipient, $itemId, $organizationid, BasicUser $createdBy){
		$content = [
			"orgId" => $organizationid
		];
		$this->eventStore->beginTransaction();
		try {
			$card = ItemOwnerChangedCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}

	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createItemMemberAddedCard()
	 */
	public function createItemMemberAddedCard(BasicUser $recipient, $itemId, BasicUser $newMember, $organizationid, BasicUser $createdBy){
		$content = [
			"orgId" => $organizationid,
			'userId' => $newMember->getId(),
			'userName' => $newMember->getFirstname().' '.$newMember->getLastname()
		];
		$this->eventStore->beginTransaction();
		try {
			$card = ItemMemberAddedCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}

	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createItemMemberRemovedCard()
	 */
	public function createItemMemberRemovedCard(BasicUser $recipient, $itemId, BasicUser $exMember, $organizationid, BasicUser $createdBy){
		$content = [
			"orgId" => $organizationid,
			'userId' => $exMember->getId(),
			'userName' => $exMember->getFirstname().' '.$exMember->getLastname()
		];
		$this->eventStore->beginTransaction();
		try {
			$card = ItemMemberRemovedCard::create($recipient, $content, $createdBy, $itemId);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}

	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::createItemMemberRemovedCard()
	 */
	public function createOrganizationMemberRoleChangedCard(BasicUser $recipient, BasicUser $member, $organizationId, $oldRole, $newRole, BasicUser $createdBy){
		$content = [
			'orgId' => $organizationId,
			'userId' => $member->getId(),
			'userName' => $member->getFirstname().' '.$member->getLastname(),
			'oldRole' => $oldRole,
			'newRole' => $newRole,
			'by' => $createdBy->getFirstname().' '.$createdBy->getLastname()
		];

		$this->eventStore->beginTransaction();
		try {
			$card = OrganizationMemberRoleChangedCard::create($recipient, $content, $createdBy);
			$this->addAggregateRoot($card);
			$this->eventStore->commit();
		} catch (\Exception $e) {
			$this->eventStore->rollback();
			throw $e;
		}
		return $card;
	}


	/**
	 * (non-PHPdoc)
	 * @see \FlowManagement\Service\FlowService::countCards()
	 */
	public function countCards(BasicUser $recipient, $filters){
		$builder = $this->entityManager->createQueryBuilder();
		$query = $builder->select('count(f)')
			->from(ReadModelFlowCard::class, 'f')
            ->leftJoin(Stream::class, 's')
			->where('f.recipient = :recipient')
			->andWhere('f.hidden = false')
        ;

        if (is_array($filters)) {
            foreach ($filters as $param => $value) {
                $paramSlug = str_replace('.', '_', $param);
                $query
                    ->andWhere("$param = :$paramSlug")
                    ->setParameter(":$paramSlug", $value);
            }
        }

        $query
            ->setParameter(':recipient', $recipient)
        ;

		return intval($query->getQuery()->getSingleScalarResult());
	}


    public function countOrgCards(User $recipient, $orgId, $filters = [])
    {
        $builder = $this->entityManager->createQueryBuilder();
        $query = $builder->select('count(f)')
            ->from(ReadModelFlowCard::class, 'f')
            ->leftJoin(Stream::class, 's')
            ->where('f.recipient = :recipient')
            ->andWhere('f.content like :orgText')
            ->andWhere('f.hidden = false')
        ;

        if (is_array($filters)) {
            foreach ($filters as $param => $value) {
                $paramSlug = str_replace('.', '_', $param);
                $query
                    ->andWhere("$param = :$paramSlug")
                    ->setParameter(":$paramSlug", $value);
            }
        }

        $query
            ->setParameter(':recipient', $recipient)
            ->setParameter(':orgText', '%orgId":"'.$orgId.'"%')
        ;

        return intval($query->getQuery()->getSingleScalarResult());
    }


	public function findFlowCardsByItem(Task $item){
		$builder = $this->entityManager->createQueryBuilder();

		$query = $builder->select('f')
			->from(ReadModelFlowCard::class, 'f')
			->innerJoin('f.item', 'i', 'WITH', 'i.id = :itemId')
			->andWhere('i.status = :status')
			->andWhere('f.hidden = 0')
			->setParameter(':itemId', $item->getId())
			->setParameter(':status', $item->getStatus());
		return $query->getQuery()->getResult();
	}
}