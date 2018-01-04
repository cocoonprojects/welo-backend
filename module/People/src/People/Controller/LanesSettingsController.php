<?php

namespace People\Controller;

use Application\Controller\OrganizationAwareController;
use Rhumsaa\Uuid\Uuid;
use Zend\View\Model\JsonModel;

class LanesSettingsController extends OrganizationAwareController
{
    protected function getCollectionOptions()
    {
        return ['GET', 'POST'];
    }

    protected function getResourceOptions()
    {
        return ['GET', 'PUT', 'POST', 'DELETE'];
    }

    public function create($data)
    {
        $organization = $this->getOrganizationService()
                             ->getOrganization($this->params('orgId'));

        $this->transaction()->begin();

        try {

            $organization->addLane(Uuid::uuid4(), $data['name'], $this->identity());

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

        $this->transaction()->begin();

        try {

            $organization->updateLane($id, $data['name'], $this->identity());

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