<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Type;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Exception\TypeException;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ModelType extends BaseType
{
    protected $expectedModelActiveRecord;

    /**
     * @param $expectedModelActiveRecord
     * @throws TypeException
     */
    public function __construct($expectedModelActiveRecord)
    {
        $class = '\\Thelia\\Model\\' . $expectedModelActiveRecord;

        if (!(class_exists($class) && new $class instanceof ActiveRecordInterface)) {
            throw new TypeException('MODEL NOT FOUND', TypeException::MODEL_NOT_FOUND);
        }

        $this->expectedModelActiveRecord = $class;
    }

    public function getType()
    {
        return 'Model type';
    }

    public function isValid($value)
    {
        return $value instanceof $this->expectedModelActiveRecord;
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? $value : null;
    }

    public function getFormType()
    {
        return 'text';
    }

    public function getFormOptions()
    {
        return [];
    }
}
