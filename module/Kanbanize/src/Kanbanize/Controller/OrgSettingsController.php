<?php

namespace Kanbanize\Controller;

use Application\Controller\OrganizationAwareController;
use Application\View\ErrorJsonModel;
use Zend\View\Model\JsonModel;

class OrgSettingsController extends OrganizationAwareController
{
    protected function getCollectionOptions()
    {
        return ['PUT', 'GET'];
    }

    protected function getResourceOptions()
    {
        return [];
    }

	public function getList()
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);

			return $this->response;
		}

		if(!$this->isAllowed($this->identity(), $this->organization, 'Kanbanize.Settings.list')) {
			$this->response->setStatusCode(403);

			return $this->response;
		}

		$organization = $this->getOrganizationService()
							 ->getOrganization($this->organization->getId());

		$orgSettings = $organization->getParams();

		if(is_null($orgSettings) || empty($orgSettings)){
			return $this->getResponse()
						->setContent(json_encode(new \stdClass()));
		}

		return new JsonModel([
			'settings' => $orgSettings->toArray()
		]);
	}

	public function replaceList($data)
	{
		if(is_null($this->identity())) {
			$this->response->setStatusCode(401);

			return $this->response;
		}

		if(!$this->isAllowed($this->identity(), $this->organization, 'Kanbanize.Settings.create')) {
			$this->response->setStatusCode(403);

			return $this->response;
		}

		$organization = $this->getOrganizationService()
							 ->getOrganization($this->organization->getId());

		try{
			$this->transaction()->begin();

			$organization->setParams($data, $this->identity());

			$this->transaction()->commit();
			$this->response->setStatusCode(202);

			return new JsonModel([
				'settings' => $organization->getParams()->toArray()
			]);

		}catch(\Exception $e){
			$this->transaction()->rollback();
			$error = new ErrorJsonModel();
			$error->setCode(500);
			$error->setDescription($e->getMessage());
			$this->response->setStatusCode(500);
			return $error;
		}
	}
}