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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TypeException;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModelValidIdType extends BaseType
{
    protected $expectedModelActiveRecordQuery;

    /**
     * @param $expectedModelActiveRecord
     *
     * @throws TypeException
     */
    public function __construct($expectedModelActiveRecord)
    {
        $class = '\\Thelia\\Model\\'.$expectedModelActiveRecord.'Query';

        if (!(class_exists($class) || !new $class() instanceof ModelCriteria)) {
            throw new TypeException('MODEL NOT FOUND', TypeException::MODEL_NOT_FOUND);
        }

        $this->expectedModelActiveRecordQuery = $class;
    }

    public function getType()
    {
        return 'Model valid Id type';
    }

    public function isValid($value)
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return null !== $queryClass::create()->findPk($value);
    }

    public function getFormattedValue($value)
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return $this->isValid($value) ? $queryClass::create()->findPk($value) : null;
    }

    public function getFormType()
    {
        return 'choice';
    }

    public function getFormOptions()
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        $query = $queryClass::create();

        if (method_exists($query, 'joinWithI18n')) {
            if (null !== $locale = Translator::getInstance()->getLocale()) {
                $query->joinWithI18n($locale);
            }
        }

        $choices = [];
        foreach ($query->find() as $item) {
            $label = method_exists($item, 'getTitle') ? $item->getTitle() : $item->getId();
            $choices[$label] = $item->getId();
        }

        return [
            'choices' => $choices,
        ];
    }
}
