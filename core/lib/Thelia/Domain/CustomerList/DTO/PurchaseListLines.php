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

namespace Thelia\Domain\CustomerList\DTO;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\CustomerList\Exception\InvalidPurchaseListException;

/**
 * The lines of a purchase list, checked once when they are built so that every
 * writer (the facade, the action, a module) receives lines the list can hold.
 *
 * References arrive from keyboards and spreadsheets: surrounding whitespace, the
 * non-breaking spaces and the invisible format characters a copy from a sheet
 * brings along are removed. A reference given twice for the same sale element
 * is kept once with the quantities added up. Case is left alone: matching it
 * against the catalog is the resolver's job.
 *
 * @implements \IteratorAggregate<int, ReferenceQuantity>
 */
final readonly class PurchaseListLines implements \Countable, \IteratorAggregate
{
    public const int MAX_LINES = 500;

    public const int MAX_REFERENCE_LENGTH = 255;

    /** @var list<ReferenceQuantity> */
    private array $lines;

    /**
     * @param iterable<ReferenceQuantity> $lines
     */
    public function __construct(iterable $lines = [])
    {
        $merged = [];

        foreach ($lines as $line) {
            $reference = self::normalizeReference($line->reference);

            if ('' === $reference) {
                throw new InvalidPurchaseListException('A purchase list line needs a reference.');
            }

            if (mb_strlen($reference) > self::MAX_REFERENCE_LENGTH) {
                throw new InvalidPurchaseListException(\sprintf('A reference is at most %d characters long.', self::MAX_REFERENCE_LENGTH));
            }

            if ($line->quantity < 1) {
                throw new InvalidPurchaseListException(\sprintf('The quantity of reference "%s" must be at least 1.', $reference));
            }

            $key = $reference."\0".($line->productSaleElementsId ?? '');
            $quantity = ($merged[$key]->quantity ?? 0) + $line->quantity;
            $merged[$key] = new ReferenceQuantity($reference, $quantity, $line->productSaleElementsId);
        }

        if (\count($merged) > self::MAX_LINES) {
            throw new InvalidPurchaseListException(\sprintf('A purchase list holds at most %d lines.', self::MAX_LINES));
        }

        $this->lines = array_values($merged);
    }

    public static function normalizeReference(string $reference): string
    {
        $reference = (string) preg_replace('/\p{Cf}+/u', '', $reference);

        return trim((string) preg_replace('/[\s\x{00A0}\x{2007}\x{202F}]+/u', ' ', $reference));
    }

    /**
     * These lines followed by the given ones, a reference already present
     * adding its quantity to the existing line.
     */
    public function merge(self $other): self
    {
        return new self([...$this->lines, ...$other->lines]);
    }

    /**
     * @return list<ReferenceQuantity>
     */
    public function all(): array
    {
        return $this->lines;
    }

    public function count(): int
    {
        return \count($this->lines);
    }

    /**
     * @return \ArrayIterator<int, ReferenceQuantity>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->lines);
    }
}
