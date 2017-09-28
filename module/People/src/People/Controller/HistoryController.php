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
                OR e.eventName = \'People\OrganizationMemberRemoved\'
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

        $role = isset($serializedEvent['payload']['newRole']) ? $serializedEvent['payload']['newRole'] : $serializedEvent['payload']['role'];

        $event = [
            "id" => $serializedEvent['id'],
            "name" => $serializedEvent['name'],
            "on" => $serializedEvent['occurredOn'],
            "user" => [
                "id" => $serializedEvent['payload']['userId'],
                "name" => "",
                "role" => $role
            ]
        ];

        return $event;
    }

}
