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

namespace Thelia\Api\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Catalog\Exception\InvalidReferenceQuantityException;

/**
 * Lines validated by {@see \Thelia\Api\Validator\ReferenceQuantityRows}, turned into
 * the lines the domain takes. What the domain still refuses once references are
 * normalized (a reference made of blanks only, too many lines once duplicates are
 * merged) is answered as a body the shop cannot take.
 */
final class ReferenceQuantityRows
{
    /**
     * @param list<array{reference: string, quantity: int, productSaleElementsId?: int|null}> $rows
     */
    public static function toLines(array $rows): ReferenceQuantityLines
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = new ReferenceQuantity(
                (string) $row['reference'],
                (int) $row['quantity'],
                isset($row['productSaleElementsId']) ? (int) $row['productSaleElementsId'] : null,
            );
        }

        try {
            return new ReferenceQuantityLines($lines);
        } catch (InvalidReferenceQuantityException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
