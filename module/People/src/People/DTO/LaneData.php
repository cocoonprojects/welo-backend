<?php

namespace People\DTO;

use Application\InvalidArgumentException;
use Zend\Filter\FilterChain;
use Zend\Filter\StringTrim;
use Zend\Filter\StripNewlines;
use Zend\Filter\StripTags;
use Zend\Validator\NotEmpty;
use Zend\Validator\StringLength;
use Zend\Validator\ValidatorChain;

class LaneData
{
    public $name;

    public static function create($data)
    {
        $name = isset($data['name']) ? $data['name'] : '';

        $filter = new FilterChain();
        $filter
            ->attach(new StringTrim())
            ->attach(new StripNewlines())
            ->attach(new StripTags());

        $name = $filter->filter($name);

        $validator = new ValidatorChain();
        $validator
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 100]));

        if (!$validator->isValid($name)) {
            throw new InvalidArgumentException("'$name' in not valid");
        }

        $dto = new static;
        $dto->name = $name;

        return $dto;
    }
}