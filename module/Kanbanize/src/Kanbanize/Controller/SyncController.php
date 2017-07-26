<?php

namespace Kanbanize\Controller;

use Zend\View\Model\JsonModel;
use ZFX\Rest\Controller\HATEOASRestfulController;

class SyncController extends HATEOASRestfulController
{
	protected static $resourceOptions = ['GET'];
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

		$rootDir = __DIR__ . '/../../../../../public';

        $result = shell_exec("php $rootDir/index.php sync");

        return new JsonModel(['result' => $result]);
	}

	protected function getCollectionOptions() {
		return self::$collectionOptions;
	}

	protected function getResourceOptions() {
		return self::$resourceOptions;
	}
}