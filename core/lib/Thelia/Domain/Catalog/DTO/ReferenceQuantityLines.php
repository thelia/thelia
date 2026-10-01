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

namespace Thelia\Domain\Catalog\DTO;

use Thelia\Domain\Catalog\Exception\InvalidReferenceQuantityException;

/**
 * References and quantities as a buyer types, imports or saves them, checked once
 * when they are built: the quick order and the purchase lists both receive lines
 * they can take.
 *
 * References arrive from keyboards and spreadsheets: surrounding whitespace, the
 * non-breaking spaces and the invisible format characters a copy from a sheet
 * brings along are removed. A reference given twice for the same sale element
 * is kept once with the quantities added up. Case is left alone: matching it
 * against the catalog is the resolver's job.
 *
 * @implements \IteratorAggregate<int, ReferenceQuantity>
 */
final readonly class ReferenceQuantityLines implements \Countable, \IteratorAggregate
{
    public const int MAX_LINES = 500;

    public const int MAX_REFERENCE_LENGTH = 255;

    /**
     * Per line, the lines of one reference added up: a list keeps it in an INTEGER
     * column, and no order by reference needs more.
     */
    public const int MAX_QUANTITY = 999_999;

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
                throw new InvalidReferenceQuantityException('A line needs a reference.');
            }

            if (mb_strlen($reference) > self::MAX_REFERENCE_LENGTH) {
                throw new InvalidReferenceQuantityException(\sprintf('A reference is at most %d characters long.', self::MAX_REFERENCE_LENGTH));
            }

            if ($line->quantity < 1) {
                throw new InvalidReferenceQuantityException(\sprintf('The quantity of reference "%s" must be at least 1.', $reference));
            }

            $key = $reference."\0".($line->productSaleElementsId ?? '');
            $quantity = self::boundedQuantity($reference, $merged[$key]->quantity ?? 0, $line->quantity);
            $merged[$key] = new ReferenceQuantity($reference, $quantity, $line->productSaleElementsId);
        }

        if (\count($merged) > self::MAX_LINES) {
            throw new InvalidReferenceQuantityException(\sprintf('At most %d lines are taken at once.', self::MAX_LINES));
        }

        $this->lines = array_values($merged);
    }

    /**
     * Checked before the addition, so that it never leaves the integers.
     */
    private static function boundedQuantity(string $reference, int $current, int $added): int
    {
        if ($added > self::MAX_QUANTITY - $current) {
            throw new InvalidReferenceQuantityException(\sprintf('The quantity of reference "%s" is at most %d.', $reference, self::MAX_QUANTITY));
        }

        return $current + $added;
    }

    public static function normalizeReference(string $reference): string
    {
        $reference = (string) preg_replace('/\p{Cf}+/u', '', $reference);

        return trim((string) preg_replace('/[\s\x{00A0}\x{2007}\x{202F}]+/u', ' ', $reference));
    }

    /**
     * These lines followed by the given ones, a reference already present
     * adding its quantity to the existing line.
     *
     * A line that names no sale element is the same reference as the one line
     * already holding it, whatever sale element that line settled on, and the
     * other way round: a given sale element settles a line that had none. Only
     * two different sale elements of one reference stay two lines. References
     * are compared regardless of case, as the resolver matches them.
     */
    public function merge(self $other): self
    {
        $lines = $this->lines;

        foreach ($other->lines as $line) {
            $index = self::onlyLineToJoin($lines, $line);

            if (null === $index) {
                $lines[] = $line;

                continue;
            }

            $lines[$index] = new ReferenceQuantity(
                $lines[$index]->reference,
                $lines[$index]->quantity + $line->quantity,
                $lines[$index]->productSaleElementsId ?? $line->productSaleElementsId,
            );
        }

        return new self($lines);
    }

    /**
     * @param list<ReferenceQuantity> $lines
     */
    private static function onlyLineToJoin(array $lines, ReferenceQuantity $line): ?int
    {
        $reference = mb_strtolower(self::normalizeReference($line->reference));
        $sameReference = array_keys(array_filter(
            $lines,
            static fn (ReferenceQuantity $current): bool => mb_strtolower($current->reference) === $reference,
        ));

        foreach ($sameReference as $index) {
            if ($lines[$index]->productSaleElementsId === $line->productSaleElementsId) {
                return $index;
            }
        }

        if (1 !== \count($sameReference)) {
            return null;
        }

        $current = $lines[$sameReference[0]];

        return null === $current->productSaleElementsId || null === $line->productSaleElementsId ? $sameReference[0] : null;
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
