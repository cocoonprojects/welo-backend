<?php

namespace Kanbanize\Controller;

use ZFX\Rest\Controller\HATEOASRestfulController;
use Zend\View\Model\ViewModel;

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

        $view = new ViewModel(['result' => $result]);
        $view->setTemplate('sync.phtml');

        return $view;
	}

	protected function getCollectionOptions() {
		return self::$collectionOptions;
	}

	protected function getResourceOptions() {
		return self::$resourceOptions;
	}
}