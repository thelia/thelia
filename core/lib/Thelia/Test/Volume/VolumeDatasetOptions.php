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

namespace Thelia\Test\Volume;

/**
 * The size and the shape of a volume dataset. Two generations with the same options on the
 * same starting database write the same rows.
 */
final readonly class VolumeDatasetOptions
{
    /**
     * Every generated row is dated from this instant backwards, not from the clock: a dataset
     * generated twice is the same dataset, down to its dates.
     */
    public const DEFAULT_BASE_DATE = '2026-11-01 08:00:00';

    /**
     * @param list<string> $locales       locales of the shop the texts are written in
     * @param list<string> $currencyCodes currencies every price is written in
     */
    public function __construct(
        public int $products,
        public int $orders,
        public int $customers,
        public int $categoryDepth,
        public int $categoriesPerLevel,
        public array $locales,
        public array $currencyCodes,
        public int $seed,
        public int $images = 8,
        public string $baseDate = self::DEFAULT_BASE_DATE,
    ) {
        if ($products < 1) {
            throw new \InvalidArgumentException('A volume dataset holds at least one product.');
        }
        if ($orders < 0 || $customers < 0) {
            throw new \InvalidArgumentException('Orders and customers cannot be negative.');
        }
        if ($orders > 0 && 0 === $customers) {
            throw new \InvalidArgumentException('Orders need at least one customer.');
        }
        if ($categoryDepth < 1 || $categoriesPerLevel < 1) {
            throw new \InvalidArgumentException('The category tree has at least one level of one category.');
        }
        if ([] === $locales || [] === $currencyCodes) {
            throw new \InvalidArgumentException('At least one locale and one currency are needed.');
        }
        if (false === \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $baseDate)) {
            throw new \InvalidArgumentException('The base date is written Y-m-d H:i:s.');
        }
        if ($images < 1) {
            throw new \InvalidArgumentException('At least one source image is needed.');
        }
    }

    public function leafCategoryCount(): int
    {
        return $this->categoriesPerLevel ** $this->categoryDepth;
    }

    public function categoryCount(): int
    {
        $count = 0;
        for ($level = 1; $level <= $this->categoryDepth; ++$level) {
            $count += $this->categoriesPerLevel ** $level;
        }

        return $count;
    }

    /**
     * @return array<string, int|string|list<string>>
     */
    public function toArray(): array
    {
        return [
            'products' => $this->products,
            'orders' => $this->orders,
            'customers' => $this->customers,
            'categoryDepth' => $this->categoryDepth,
            'categoriesPerLevel' => $this->categoriesPerLevel,
            'locales' => $this->locales,
            'currencies' => $this->currencyCodes,
            'seed' => $this->seed,
            'images' => $this->images,
            'baseDate' => $this->baseDate,
        ];
    }
}
