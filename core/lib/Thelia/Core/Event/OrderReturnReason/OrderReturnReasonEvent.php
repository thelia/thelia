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

namespace Thelia\Core\Event\OrderReturnReason;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderReturnReason;

/**
 * What a merchant declares about a return reason, as the configuration screen
 * collects it. The reason itself is only attached once the action has written it.
 */
class OrderReturnReasonEvent extends ActionEvent
{
    protected ?string $code = null;

    protected bool $visible = true;

    protected string $locale = 'en_US';

    protected ?string $title = null;

    protected ?string $description = null;

    protected ?OrderReturnReason $orderReturnReason = null;

    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return $this
     */
    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return $this
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getOrderReturnReason(): ?OrderReturnReason
    {
        return $this->orderReturnReason;
    }

    /**
     * @return $this
     */
    public function setOrderReturnReason(?OrderReturnReason $orderReturnReason): self
    {
        $this->orderReturnReason = $orderReturnReason;

        return $this;
    }
}
