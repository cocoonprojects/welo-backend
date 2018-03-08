<?php

namespace Accounting\Service;



class StatementsDumper
{
    static private $header = [
        'Data',
        'Pagante',
        'Ricevente',
        'Crediti',
        'Motivo'
    ];


    public function dump(array $results, array $filters, $writer)
    {
        foreach ($results as $result) {

            $data = [
                'date' => $result->getCreatedAt()->format('Y-m-d G:i:s'),
                'payer' => $this->getAccountName($result->getPayer()),
                'payee' => $this->getAccountName($result->getPayee()),
                'credits' => number_format($result->getBalance(), 1, ',', '.'),
                'description' => $result->getDescription()
            ];
            $writer->writeLine(array_values($data));
        }
    }


    protected function getAccountName($account)
    {
        if (!$account) {
            return "";
        }

        return $account->getName();
    }
}