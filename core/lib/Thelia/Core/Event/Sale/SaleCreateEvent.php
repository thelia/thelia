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

namespace Thelia\Core\Event\Sale;

use Thelia\Model\Sale;

/**
 * Class SaleCreateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleCreateEvent extends SaleEvent
{
    protected $title;
    protected $saleLabel;
    protected $locale;

    /**
     * The audience of a new operation, and its countdown, default to what a shop that
     * knows nothing about either would get: open to everyone, no countdown. A back
     * office or an API client that does not post these fields keeps the old behaviour.
     */
    protected int $audienceMode = Sale::AUDIENCE_MODE_PUBLIC;

    protected bool $hideProducts = false;

    protected int $countdownMode = Sale::COUNTDOWN_MODE_NONE;

    protected ?int $countdownLeadHours = null;

    /** @var array<int|string, int|string> the IDs of the customers the operation is reserved for */
    protected array $customerIds = [];

    /**
     * @return SaleCreateEvent $this
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return $this
     */
    public function setSaleLabel(string $saleLabel): static
    {
        $this->saleLabel = $saleLabel;

        return $this;
    }

    public function getSaleLabel(): string
    {
        return $this->saleLabel;
    }

    /**
     * @param int $audienceMode one of the Sale::AUDIENCE_MODE_* constants
     *
     * @return $this
     */
    public function setAudienceMode(int $audienceMode): static
    {
        $this->audienceMode = $audienceMode;

        return $this;
    }

    public function getAudienceMode(): int
    {
        return $this->audienceMode;
    }

    /**
     * @return $this
     */
    public function setHideProducts(bool $hideProducts): static
    {
        $this->hideProducts = $hideProducts;

        return $this;
    }

    public function getHideProducts(): bool
    {
        return $this->hideProducts;
    }

    /**
     * @param int $countdownMode one of the Sale::COUNTDOWN_MODE_* constants
     *
     * @return $this
     */
    public function setCountdownMode(int $countdownMode): static
    {
        $this->countdownMode = $countdownMode;

        return $this;
    }

    public function getCountdownMode(): int
    {
        return $this->countdownMode;
    }

    /**
     * @return $this
     */
    public function setCountdownLeadHours(?int $countdownLeadHours): static
    {
        $this->countdownLeadHours = $countdownLeadHours;

        return $this;
    }

    public function getCountdownLeadHours(): ?int
    {
        return $this->countdownLeadHours;
    }

    /**
     * @param array<int|string, int|string> $customerIds the IDs of the customers the operation is reserved for
     *
     * @return $this
     */
    public function setCustomerIds(array $customerIds): static
    {
        $this->customerIds = $customerIds;

        return $this;
    }

    /**
     * @return array<int|string, int|string>
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }
}
