<?php

namespace Kanbanize\Controller;

use Application\Controller\OrganizationAwareController;

class SyncController extends OrganizationAwareController
{
	protected static $resourceOptions = [];
	protected static $collectionOptions= ['GET'];

	public function getList()
	{
		if($this->identity() === null) {
			$this->response->setStatusCode(401);

			return $this->response;
		}

		if(!$this->identity()->isAdmin()) {
			$this->response->setStatusCode(403);
			return $this->response;
		}

		$rootDir = __DIR__ . '../../../../../public';

        $result = shell_exec("php $rootDir/index.php sync");

        return $result;
	}

	protected function getCollectionOptions() {
		return self::$collectionOptions;
	}

	protected function getResourceOptions() {
		return self::$resourceOptions;
	}
}