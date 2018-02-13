<?php
/**
 * Created by PhpStorm.
 * User: andreabandera
 * Date: 03/11/15
 * Time: 23:27
 */

namespace Accounting\Controller;


use Accounting\Service\AccountService;
use Application\Controller\OrganizationAwareController;
use People\Service\OrganizationService;
use Zend\View\Model\JsonModel;

class MembersController extends OrganizationAwareController
{
	protected static $resourceOptions = ['GET'];
	/**
	 * @var AccountService
	 */
	private $accountService;

	/**
	 * MembersController constructor.
	 * @param OrganizationService $organizationService
	 * @param AccountService $accountService
	 */
	public function __construct(OrganizationService $organizationService, AccountService $accountService)
	{
		parent::__construct($organizationService);
		$this->accountService = $accountService;
	}

	/**
	 * Return single resource
	 *
	 * @param  string $id
	 * @return mixed
	 */
	public function get($id)
	{
		if (is_null($this->identity())) {
			$this->response->setStatusCode(401);

			return $this->response;
		}

		$account = $this->accountService
                        ->findPersonalAccount($id, $this->organization);

        if (is_null($account)) {
            $this->response->setStatusCode(404);

            return $this->response;
        }

        if (!$this->isAllowed($this->identity(), $account, 'Accounting.Account.get')) {
            $this->response->setStatusCode(403);

            return $this->response;
        }

        $endOn = $this->getDateTimeParam('endOn');

        if (is_null($endOn)) {
            $endOn = new \DateTimeImmutable();
        }

        $transactions = $this->accountService
                             ->findTransactions($account, null, null, ['endOn' => $endOn]);

        $totalGeneratedCredits = 0;

        //Date Limits
        $dateLimitThreeMonths = $endOn->sub(new \DateInterval('P3M'));
        $dateLimitSixMonths = $endOn->sub(new \DateInterval('P6M'));
        $dateLimitOneYear = $endOn->sub(new \DateInterval('P1Y'));

        $lastThreeMonthsCredits = 0;
        $lastSixMonthsCredits = 0;
        $lastYearCredits = 0;

        foreach ($transactions as $t) {

            if ($t->getAmount() >= 0 || $t->isRevert()) {
                $totalGeneratedCredits += $t->getAmount();

                if ($t->getCreatedAt() > $dateLimitThreeMonths) {
                    $lastThreeMonthsCredits += $t->getAmount();//Last 3 Months
                }

                if ($t->getCreatedAt() > $dateLimitSixMonths) {
                    $lastSixMonthsCredits += $t->getAmount();//Last 6 Months
                }

                if ($t->getCreatedAt() > $dateLimitOneYear) {
                    $lastYearCredits += $t->getAmount();//Last year
                }
            }
        }

        return new JsonModel([
            'balance' => $account->getBalance()->getValue(),
            'total'   => $totalGeneratedCredits,
            'last3M'  => $lastThreeMonthsCredits,
            'last6M'  => $lastSixMonthsCredits,
            'last1Y'  => $lastYearCredits
        ]);
	}

	/**
	 * @return array
	 */
	protected function getResourceOptions()
	{
		return self::$resourceOptions;
	}
}