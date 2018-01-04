<?php

namespace People\Controller;

use Application\Controller\OrganizationAwareController;
use People\DTO\LaneData;
use People\Service\OrganizationService;
use Rhumsaa\Uuid\Uuid;
use TaskManagement\Service\TaskService;
use Zend\View\Model\JsonModel;

class LanesSettingsController extends OrganizationAwareController
{
    protected $taskService;

    protected function getCollectionOptions()
    {
        return ['GET', 'POST'];
    }

    protected function getResourceOptions()
    {
        return ['GET', 'PUT', 'POST', 'DELETE'];
    }

    public function __construct(OrganizationService $organizationService, TaskService $taskService)
    {
        parent::__construct($organizationService);

        $this->taskService = $taskService;
    }

    public function create($data)
    {
        $organization = $this->getOrganizationService()
                             ->getOrganization($this->params('orgId'));

        if (!$this->isAllowed($this->identity(), $this->organization, 'People.Organization.manageLanes')) {
            $this->response->setStatusCode(403);

            return $this->response;
        }

        $this->transaction()->begin();

        try {

            $organization->addLane(
                Uuid::uuid4(),
                LaneData::create($data),
                $this->identity()
            );

            $this->transaction()->commit();
            $this->response->setStatusCode(201);

        } catch (\Exception $e) {
            print_r($e->getMessage());
            $this->transaction()->rollback();
            $this->response->setStatusCode(204);
        }

        return $this->response;
    }

    public function update($id, $data)
    {
        $organization = $this->getOrganizationService()
            ->getOrganization($this->params('orgId'));

        if (!$this->isAllowed($this->identity(), $this->organization, 'People.Organization.manageLanes')) {
            $this->response->setStatusCode(403);

            return $this->response;
        }

        $this->transaction()->begin();

        try {

            $organization->updateLane(
                $id,
                LaneData::create($data),
                $this->identity()
            );

            $this->transaction()->commit();
            $this->response->setStatusCode(200);

        } catch (\Exception $e) {
            print_r($e->getMessage());
            $this->transaction()->rollback();
            $this->response->setStatusCode(204);
        }

        return $this->response;
    }

    public function delete($id)
    {
        $organization = $this->getOrganizationService()
            ->getOrganization($this->params('orgId'));

        if (!$this->isAllowed($this->identity(), $this->organization, 'People.Organization.manageLanes')) {
            $this->response->setStatusCode(403);

            return $this->response;
        }

        $count = $this->taskService->countItemsInLane($id);

        if ($count) {
            $this->response->setStatusCode(409);
            return $this->response;
        }

        $this->transaction()->begin();

        try {

            $organization->deleteLane($id, $this->identity());

            $this->transaction()->commit();
            $this->response->setStatusCode(200);

        } catch (\Exception $e) {
            print_r($e->getMessage());
            $this->transaction()->rollback();
            $this->response->setStatusCode(204);
        }

        return $this->response;
    }

    public function get($id)
    {
    }

	public function getList()
	{
        $lanes = $this->getOrganizationService()
            ->findOrganization($this->params('orgId'))
            ->getSortedLanes();

        return new JsonModel($lanes);
	}

}