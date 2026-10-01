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

namespace Thelia\Domain\QuickOrder\DTO;

use Thelia\Domain\QuickOrder\Enum\LineStatus;

/**
 * One line of the control table: what the buyer asked for and what the shop
 * made of it. Prices are unit prices, the customer discount included.
 */
final readonly class QuickOrderLine
{
    /**
     * @param list<Candidate> $candidates
     */
    public function __construct(
        public string $reference,
        public int $quantity,
        public LineStatus $status,
        public ?int $productSaleElementsId = null,
        public ?int $productId = null,
        public ?string $title = null,
        public ?float $untaxedUnitPrice = null,
        public ?float $taxedUnitPrice = null,
        public bool $promo = false,
        public ?float $availableQuantity = null,
        public array $candidates = [],
        public bool $added = false,
    ) {
    }

    public function markAdded(): self
    {
        return new self(
            $this->reference,
            $this->quantity,
            $this->status,
            $this->productSaleElementsId,
            $this->productId,
            $this->title,
            $this->untaxedUnitPrice,
            $this->taxedUnitPrice,
            $this->promo,
            $this->availableQuantity,
            $this->candidates,
            true,
        );
    }

    /**
     * The line the cart turned down: what it still takes of this sale element,
     * once what it already holds is counted.
     */
    public function refusedByTheCart(float $availableQuantity): self
    {
        return new self(
            $this->reference,
            $this->quantity,
            LineStatus::QuantityRefused,
            $this->productSaleElementsId,
            $this->productId,
            $this->title,
            $this->untaxedUnitPrice,
            $this->taxedUnitPrice,
            $this->promo,
            max(0.0, $availableQuantity),
            $this->candidates,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'productSaleElementsId' => $this->productSaleElementsId,
            'productId' => $this->productId,
            'title' => $this->title,
            'untaxedUnitPrice' => $this->untaxedUnitPrice,
            'taxedUnitPrice' => $this->taxedUnitPrice,
            'promo' => $this->promo,
            'availableQuantity' => $this->availableQuantity,
            'candidates' => array_map(static fn (Candidate $candidate): array => $candidate->toArray(), $this->candidates),
            'added' => $this->added,
        ];
    }
}
