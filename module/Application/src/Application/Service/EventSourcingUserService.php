<?php
namespace Application\Service;

use Rhumsaa\Uuid\Uuid;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Doctrine\ORM\EntityManager;
use Application\Entity\User;

class EventSourcingUserService implements UserService, EventManagerAwareInterface
{
    protected $events;

    protected $entityManager;
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    public function subscribeUser($userInfo, $role = User::ROLE_USER)
    {
        $user = User::createUser(
            Uuid::uuid4(),
            $userInfo['email'],
            $userInfo['given_name'],
            $userInfo['family_name'],
            isset($userInfo['picture']) ? $userInfo['picture'] : null,
            $role
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush($user);

        $this->getEventManager()->trigger(User::EVENT_CREATED, $user);

        return $user;
    }

    public function saveUser(User $user)
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush($user);

        return $user;
    }

    public function findUser($id)
    {
        $user = $this->entityManager
                     ->getRepository(User::class)
                     ->findOneBy(array("id" => $id));

        return $user;
    }

    public function findByIds(array $ids)
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery();

        return $query->getResult();
    }

    public function findUserByEmail($email)
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :mainEmail')
            ->orWhere("u.secondaryEmails LIKE :secEmail")
            ->setParameter('mainEmail', $email)
            ->setParameter('secEmail', "%$email%")
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    public function findUserByMainEmail($email)
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :mainEmail')
            ->setParameter('mainEmail', $email)
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    public function findUsers($filters)
    {
        $builder = $this->entityManager->createQueryBuilder();
        $query = $builder->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.mostRecentEditAt', 'DESC');

        if (isset($filters["kanbanizeusername"])) {
            $query->andWhere('u. kanbanizeUsername = :username')
                  ->setParameter('username', $filters["kanbanizeusername"]);
        }

        return $query->getQuery()->getResult();
    }
    
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            'Application\UserService',
            __CLASS__,
            get_class($this)
        ));
        $this->events = $events;
    }

    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    public function refreshEntity(User $user)
    {
        $this->entityManager->refresh($user);
    }
}
