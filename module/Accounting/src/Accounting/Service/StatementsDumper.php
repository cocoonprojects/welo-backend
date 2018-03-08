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

/*
            #payer: Accounting\Entity\OrganizationAccount {#1946}
            #payee: Accounting\Entity\PersonalAccount {#2197}
            #amount: -150
            #description: "Beccate sti du crediti"
            #balance: 350
            #createdAt: DateTime {#2744
            +"date": "2018-03-08 15:01:59.000000"
            +"timezone_type": 3
            +"timezone": "Europe/Rome"
  */

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