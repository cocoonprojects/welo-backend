<?php

namespace Application\Service;

class CsvWriter
{
    protected $separator;

    protected $tmpfile;


    public function __construct()
    {
    }

    public function setSeparator($separator = ';')
    {
        $this->separator = $separator;
    }


    public function setFileName($filename)
    {
        $this->tmpfile = new \SplFileObject($filename, 'w+');
    }


    public function writeLine(array $data)
    {
        $this->tmpfile->fputcsv($data, $this->separator);
    }
}