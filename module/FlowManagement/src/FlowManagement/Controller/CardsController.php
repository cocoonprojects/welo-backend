<?php
namespace FlowManagement\Controller;

use Application\Service\FrontendRouter;
use Rhumsaa\Uuid\Uuid;
use ZFX\Rest\Controller\HATEOASRestfulController;
use FlowManagement\Service\FlowService;
use Zend\Validator\ValidatorChain;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\GreaterThan;
use Zend\View\Model\JsonModel;

class CardsController extends HATEOASRestfulController {

	const DEFAULT_FLOW_ITEMS_LIMIT = 10;

	protected static $collectionOptions = ['GET'];
	protected static $resourceOptions = [];

	private $flowService;

	protected $listLimit = self::DEFAULT_FLOW_ITEMS_LIMIT;

	public function __construct(FlowService $flowService){
		$this->flowService = $flowService;
	}

	public function getList()
    {
        if (is_null($this->identity())) {
            $this->response->setStatusCode(401);

            return $this->response;
        }

        $filters = [];
        $integerValidator = new ValidatorChain();
        $integerValidator
            ->attach(new IsInt())
            ->attach(new GreaterThan(['min' => 0, 'inclusive' => false]));
        $offset = $this->getRequest()->getQuery("offset");
        $offset = $integerValidator->isValid($offset) ? intval($offset) : 0;
        $limit = $this->getRequest()->getQuery("limit");
        $limit = $integerValidator->isValid($limit) ? intval($limit) : $this->getListLimit();

        $orgId = $this->getRequest()->getQuery("org");
        if (Uuid::isValid($orgId)) {
            $flowCards = $this->flowService->findOrgFlowCards($this->identity(), $orgId, $offset, $limit, $filters);
            $totalCards = $this->flowService->countOrgCards($this->identity(), $orgId, $filters);
        } else {
            $flowCards = $this->flowService->findFlowCards($this->identity(), $offset, $limit, $filters);
            $totalCards = $this->flowService->countCards($this->identity(), $filters);
        }

        $count = count($flowCards);
        $serializer = function ($flowCard) {
            return $flowCard->serialize();
        };

        $hal['count'] = $count;
        $hal['total'] = $totalCards;

        if ($hal['count'] < $hal['total']) {
            $hal['_links']['next']['href'] = $this->url()->fromRoute('flow');
        }
        $hal['_links']['self']['href'] = $this->url()->fromRoute('flow');
        $hal['_embedded']['ora:flowcard'] = $count ? array_column(array_map($serializer, $flowCards), null, 'id') : new \stdClass();

        return new JsonModel($hal);
	}

	protected function getCollectionOptions(){
		return self::$collectionOptions;
	}

	protected function getResourceOptions(){
		return self::$resourceOptions;
	}

	public function getFlowService(){
		return $this->flowService;
	}

	public function setListLimit($size){
		if(is_int($size)){
			$this->listLimit = $size;
		}
	}

	public function getListLimit(){
		return $this->listLimit;
	}
}