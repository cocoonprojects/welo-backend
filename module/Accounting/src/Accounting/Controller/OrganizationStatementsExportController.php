<?php

namespace Accounting\Controller;

use Accounting\Service\AccountService;
use Accounting\Service\StatementsDumper;
use Application\Controller\OrganizationAwareController;
use Application\Service\CsvWriter;
use People\Service\OrganizationService;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\GreaterThan;
use Zend\Validator\ValidatorChain;

class OrganizationStatementsExportController extends OrganizationAwareController
{
    protected $mailService;

    protected $organizationService;

    protected $accountService;

    protected $csvWriter;


    public function __construct(OrganizationService $organizationService, AccountService $accountService, CsvWriter $csvWriter)
    {
        parent::__construct($organizationService);
        $this->organizationService = $organizationService;
        $this->accountService = $accountService;
        $this->csvWriter = $csvWriter;
    }


    public function getList()
    {
        if(is_null($this->identity())) {
            $this->response->setStatusCode(401);
            return $this->response;
        }

        $validator = new ValidatorChain();
        $validator->attach(new IsInt())
            ->attach(new GreaterThan(['min' => 0, 'inclusive' => false]));

        $offset = $validator->isValid($this->getRequest()->getQuery("offset")) ? intval($this->getRequest()->getQuery("offset")) : 0;
        $limit = $validator->isValid($this->getRequest()->getQuery("limit")) ? intval($this->getRequest()->getQuery("limit")) : 500;

        $this->dump($offset, $limit);

        return $this->response;
    }


    protected function dump($offset, $limit)
    {
        $account = $this->accountService->findOrganizationAccount($this->organization);
        if(is_null($account)) {
            $this->response->setStatusCode(404);
            return $this->response;
        }

        if(!$this->isAllowed($this->identity(), $account, 'Accounting.Account.statement')) {
            $this->response->setStatusCode(403);
            return $this->response;
        }

        $results = $this->accountService->findTransactions($account, $limit, $offset);

        $tmpname = tempnam(sys_get_temp_dir(), 'export');
        $this->csvWriter->setFileName($tmpname);
        $this->csvWriter->setSeparator(';');

        $dumper = new StatementsDumper();
        $dumper->dump($results, [], $this->csvWriter);

        dump(file_get_contents($tmpname));

        $this->response
            ->setContent(file_get_contents($tmpname))
            ->setStatusCode(200);

        $this->response
            ->getHeaders()
            ->addHeaders([
                'Content-Disposition\'' => 'inline; filename=export.csv',
                'Content-type' => 'text/csv',
            ]);

        unlink($tmpname);
    }


    protected function getCollectionOptions()
    {
        return ['GET'];
    }

    protected function getResourceOptions()
    {
        return [];
    }

}