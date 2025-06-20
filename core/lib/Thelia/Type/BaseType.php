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

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
abstract class BaseType implements TypeInterface
{
    abstract public function getType();

    abstract public function isValid($value);

    abstract public function getFormattedValue($value);

    abstract public function getFormOptions();

    public function getFormType()
    {
        return TextType::class;
    }

    public function verifyForm($value, ExecutionContextInterface $context): void
    {
        if (!$this->isValid($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'received value `%value` does not match `%type` type',
                    [
                        '%value' => $value,
                        '%type' => $this->getType(),
                    ]
                )
            );
        }
    }
}
