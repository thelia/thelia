<?php

declare(strict_types=1);

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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TypeException;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModelValidIdType extends BaseType
{
    protected string $expectedModelActiveRecordQuery;

    /**
     * @throws TypeException
     */
    public function __construct(string $expectedModelActiveRecord)
    {
        $class = '\\Thelia\\Model\\'.$expectedModelActiveRecord.'Query';

        if (!class_exists($class) && new $class() instanceof ModelCriteria) {
            throw new TypeException('MODEL NOT FOUND', TypeException::MODEL_NOT_FOUND);
        }

        $this->expectedModelActiveRecordQuery = $class;
    }

    public function getType(): string
    {
        return 'Model valid Id type';
    }

    public function isValid($value): bool
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return null !== $queryClass::create()->findPk($value);
    }

    public function getFormattedValue($value)
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return $this->isValid($value) ? $queryClass::create()->findPk($value) : null;
    }

    public function getFormType(): string
    {
        return ChoiceType::class;
    }

    public function getFormOptions(): array
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        $query = $queryClass::create();

        if (method_exists($query, 'joinWithI18n') && null !== $locale = Translator::getInstance()->getLocale()) {
            $query->joinWithI18n($locale);
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
