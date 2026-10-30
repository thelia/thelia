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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Thelia\Api\Resource\Product as ProductResource;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDecimal;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Model\Product as ProductModel;
use Thelia\Model\ProductQuery;

/**
 * The same refusals as the events of the catalogue: BundleCompositionService judges the
 * settings, on a copy of the stored product that is never saved.
 */
class BundleSettingsConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly BundleCompositionService $bundleCompositionService)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BundleSettingsConstraint) {
            throw new UnexpectedTypeException($constraint, BundleSettingsConstraint::class);
        }

        if (!$value instanceof ProductResource) {
            throw new UnexpectedTypeException($value, ProductResource::class);
        }

        if (null === $value->isBundle && null === $value->bundlePriceMode && null === $value->bundleDiscount && null === $value->bundleDiscountType) {
            return;
        }

        $stored = null === $value->getId() ? null : ProductQuery::create()->findPk($value->getId());
        $probe = null === $stored ? new ProductModel() : clone $stored;

        try {
            $this->bundleCompositionService->applySettings(
                $probe,
                $value->isBundle,
                null === $value->bundlePriceMode ? null : BundlePriceMode::tryFrom($value->bundlePriceMode),
                null === $value->bundleDiscount ? null : BundleDecimal::normalize($value->bundleDiscount),
                null === $value->bundleDiscountType ? null : BundleDiscountType::tryFrom($value->bundleDiscountType),
            );
        } catch (BundleDefinitionException|\InvalidArgumentException $exception) {
            $this->context->addViolation($exception->getMessage());
        }
    }
}
