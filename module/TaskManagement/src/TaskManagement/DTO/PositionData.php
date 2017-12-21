<?php

namespace TaskManagement\DTO;

use Application\InvalidArgumentException;
use Zend\I18n\Validator\IsInt;
use Zend\Validator\ValidatorChain;

class PositionData
{
    public $data;

    public static function fromArray(array $data)
    {
        $integerValidator = new ValidatorChain();
        $integerValidator
            ->attach(new IsInt());

        foreach ($data as $id => $position) {
            if (!$integerValidator->isValid($position)) {
                throw new InvalidArgumentException("'$position' in not an integer");
            }
        }

        $dto = new static;
        $dto->data = $data;

        return $dto;
    }
}