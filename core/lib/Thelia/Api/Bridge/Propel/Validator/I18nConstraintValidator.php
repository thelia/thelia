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

namespace Thelia\Api\Bridge\Propel\Validator;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Api\Resource\I18nCollection;

class I18nConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly RequestStack $requestStack)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof I18nCollection) {
            throw new \RuntimeException('I18nConstraint attribute should be used on'.I18nCollection::class);
        }

        $request = $this->requestStack->getMainRequest();
        $method = $request?->getMethod();

        if (!$method || HttpOperation::METHOD_PATCH === $method) {
            return;
        }

        $titleAndLocaleCount = 0;

        /** @var I18nCollection $i18nData */
        $i18nData = $value;

        foreach ($i18nData->i18ns as $i18n) {
            if (null !== $i18n->getTitle() && !empty($i18n->getTitle())) {
                ++$titleAndLocaleCount;
            }
        }

        if (0 === $titleAndLocaleCount) {
            $this->context->buildViolation(
                $this->translator->trans(
                    'The title and locale must be defined at least once.',
                    [],
                    null,
                    'en_US',
                ),
            )->addViolation();
        }
    }
}
