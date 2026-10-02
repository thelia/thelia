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
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Product\Identifier\Gtin;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Refuses on the API, as a 422 naming the field, what the model would refuse on save.
 */
class GtinConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GtinConstraint) {
            throw new UnexpectedTypeException($constraint, GtinConstraint::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $normalizedCode = Gtin::normalize($value);
        $violation = '' === $normalizedCode ? null : Gtin::violationOf($normalizedCode);

        if (null === $violation || $this->isTheStoredCode($value)) {
            return;
        }

        $this->context->buildViolation(InvalidGtinException::describe($value, $violation))
            ->setCode($violation->value)
            ->addViolation();
    }

    private function isTheStoredCode(string $value): bool
    {
        $resource = $this->context->getObject();

        if (!$resource instanceof ProductSaleElementsResource || null === $resource->getId()) {
            return false;
        }

        // Read from the table, not from the instance pool, which may already hold the
        // value being written.
        return $value === ProductSaleElementsQuery::create()
            ->filterById($resource->getId())
            ->select([ProductSaleElementsTableMap::COL_EAN_CODE])
            ->findOne();
    }
}
