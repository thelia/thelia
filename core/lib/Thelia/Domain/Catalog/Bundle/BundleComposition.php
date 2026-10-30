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

namespace Thelia\Domain\Catalog\Bundle;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;

/**
 * What a customer puts in a bundle: the quantity of each component, by bundle item. A
 * component with no quantity is out of the bundle, so a quantity of zero is not kept and
 * two spellings of one choice are one value.
 *
 * Canonical JSON, stored in `cart_item.bundle_composition` and the only thing that tells two
 * lines of the same bundle apart: the lines sorted by bundle item id, a decimal quantity as a
 * string without trailing zeros.
 *
 *     [{"bundleItemId":12,"quantity":"1"},{"bundleItemId":13,"quantity":"2"}]
 *
 * It holds no price: a price is always read from the catalogue.
 */
#[Exclude]
final readonly class BundleComposition
{
    /**
     * The most of one component a customer may ask for in one bundle. The input is the one of
     * an anonymous visitor: it is bounded here so that no arithmetic downstream sees a
     * quantity it cannot hold.
     */
    public const MAX_ITEM_QUANTITY = 1_000_000;

    /**
     * The most lines a composition may have: a bundle has a handful of items, and a list of
     * thousands is not a choice a customer made.
     */
    public const MAX_LINES = 500;

    /**
     * @param list<BundleCompositionLine> $lines sorted by bundle item id, one line per item, no zero quantity
     */
    private function __construct(public array $lines)
    {
    }

    /**
     * What a caller sends is untrusted, so each line is checked for its shape: it is typed
     * as mixed, and the lines that are not `{bundleItemId, quantity}` are refused.
     *
     * @param list<mixed> $lines each one `array{bundleItemId: int|string, quantity: int|float|string}`
     *
     * @throws InvalidBundleCompositionException
     */
    public static function fromArray(array $lines): self
    {
        if (\count($lines) > self::MAX_LINES) {
            throw InvalidBundleCompositionException::malformed(\sprintf('at most %d lines', self::MAX_LINES));
        }

        $quantities = [];

        foreach ($lines as $line) {
            if (!\is_array($line) || !isset($line['bundleItemId'], $line['quantity'])) {
                throw InvalidBundleCompositionException::malformed('a line needs a bundleItemId and a quantity');
            }

            $bundleItemId = \is_int($line['bundleItemId']) || \is_string($line['bundleItemId'])
                ? filter_var($line['bundleItemId'], \FILTER_VALIDATE_INT)
                : false;

            if (false === $bundleItemId || $bundleItemId <= 0) {
                throw InvalidBundleCompositionException::malformed('bundleItemId is a positive integer');
            }

            if (!\is_string($line['quantity']) && !\is_int($line['quantity']) && !\is_float($line['quantity'])) {
                throw InvalidBundleCompositionException::malformed('quantity is a decimal');
            }

            if (isset($quantities[$bundleItemId])) {
                throw InvalidBundleCompositionException::malformed(\sprintf('bundle item %d appears twice', $bundleItemId));
            }

            try {
                $micro = BundleDecimal::toMicro($line['quantity']);
            } catch (\InvalidArgumentException $exception) {
                throw InvalidBundleCompositionException::malformed($exception->getMessage());
            }

            if ($micro > self::MAX_ITEM_QUANTITY * BundleDecimal::SCALE) {
                throw InvalidBundleCompositionException::malformed(\sprintf('a quantity is at most %d', self::MAX_ITEM_QUANTITY));
            }

            $quantities[$bundleItemId] = $micro;
        }

        ksort($quantities);
        $kept = [];

        foreach ($quantities as $bundleItemId => $micro) {
            if ($micro > 0) {
                $kept[] = new BundleCompositionLine($bundleItemId, BundleDecimal::fromMicro($micro));
            }
        }

        return new self($kept);
    }

    /**
     * @throws InvalidBundleCompositionException
     */
    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidBundleCompositionException::malformed('not valid JSON');
        }

        if (!\is_array($decoded) || !array_is_list($decoded)) {
            throw InvalidBundleCompositionException::malformed('a list of lines is expected');
        }

        return self::fromArray($decoded);
    }

    /**
     * @return list<array{bundleItemId: int, quantity: string}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (BundleCompositionLine $line): array => ['bundleItemId' => $line->bundleItemId, 'quantity' => $line->quantity],
            $this->lines,
        );
    }

    public function toCanonicalJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR);
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines;
    }

    /**
     * The quantity of the item as a decimal string, "0" when the item is out of the composition.
     */
    public function quantityOf(int $bundleItemId): string
    {
        foreach ($this->lines as $line) {
            if ($line->bundleItemId === $bundleItemId) {
                return $line->quantity;
            }
        }

        return '0';
    }
}
