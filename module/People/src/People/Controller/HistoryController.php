<?php

namespace People\Controller;

use Application\Entity\EventStream;
use Doctrine\ORM\EntityManager;
use ZFX\Rest\Controller\HATEOASRestfulController;
use Zend\View\Model\JsonModel;

class HistoryController extends HATEOASRestfulController {

    protected static $collectionOptions = ['GET'];
    protected static $resourceOptions = ['GET'];

    private $entityManager;

    public function __construct(EntityManager $entityManager){
        $this->entityManager = $entityManager;
    }

    public function get($id) {
        $orgId = $this->params('orgId');
        $builder = $this->entityManager->createQueryBuilder();

        $query = $builder->select('e')
            ->from(EventStream::class, 'e')
            ->where('e.aggregate_id = :id')
            ->andWhere('e.aggregate_type = :type')
            ->andWhere('(
                e.eventName = \'People\OrganizationMemberAdded\' 
                OR e.eventName = \'People\OrganizationMemberRoleChanged\'
                OR e.eventName = \'People\Event\OrganizationMemberRemoved\'
                OR e.eventName = \'People\Event\OrganizationMemberActivationChanged\'
            )')
            ->setParameter(':id', $orgId)
            ->setParameter(':type', 'People\Organization')
            ->getQuery();

        $result = array_map([$this, 'serializeOne'], $query->getResult());

        $result = array_values(array_filter($result, function($event) use ($id) {
            return $event['user']['id']== $id;
        } ));

        return new JsonModel($result);
    }

    protected function getCollectionOptions()
    {
        return self::$collectionOptions;
    }

    protected function getResourceOptions()
    {
        return self::$resourceOptions;
    }

    protected function serializeOne($event)
    {
        $serializedEvent = $event->serialize();

        $userId = isset($serializedEvent['payload']['userId']) ? $serializedEvent['payload']['userId'] : 'n/a';
        $role = $this->getEventRole($serializedEvent);
        $userName = isset($serializedEvent['payload']['userName']) ? $serializedEvent['payload']['userName'] : 'n/a';
        $active = isset($serializedEvent['payload']['active']) ? isset($serializedEvent['payload']['active']) : 'n/a';

        $event = [
            "id" => $serializedEvent['id'],
            "name" => $serializedEvent['name'],
            "on" => \DateTime::createFromFormat('Y-m-d G:i:s', $serializedEvent['occurredOn'])->format('c'),
            "user" => [
                "id" => $userId,
                "name" => $userName,
                "role" => $role,
                "active" => $active
            ]
        ];

        return $event;
    }

    protected function getEventRole($serializedEvent)
    {
        $role = '';
        if (isset($serializedEvent['payload']['newRole'])) {
            $role = $serializedEvent['payload']['newRole'];
        }
        if (isset($serializedEvent['payload']['role'])) {
            $role = $serializedEvent['payload']['role'];
        }
        return $role;
    }

}
