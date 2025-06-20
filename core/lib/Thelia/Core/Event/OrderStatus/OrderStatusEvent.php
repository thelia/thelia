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
namespace Thelia\Core\Event\OrderStatus;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderStatus;

/**
 * Class OrderStatusEvent.
 *
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class OrderStatusEvent extends ActionEvent
{
    /** @var string */
    protected $code;

    /** @var string */
    protected $title;

    /** @var string */
    protected $description;

    /** @var string */
    protected $chapo;

    /** @var string */
    protected $postscriptum;

    /** @var string */
    protected $color;

    /** @var string */
    protected $locale = 'en_US';

    /** @var OrderStatus */
    protected $orderStatus;

    /** @var int */
    protected $position;

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition($position): static
    {
        $this->position = $position;

        return $this;
    }

    public function hasOrderStatus(): bool
    {
        return null !== $this->orderStatus;
    }

    /**
     * @return OrderStatus
     */
    public function getOrderStatus()
    {
        return $this->orderStatus;
    }

    /**
     * @param OrderStatus $orderStatus
     */
    public function setOrderStatus($orderStatus): static
    {
        $this->orderStatus = $orderStatus;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param string $color
     */
    public function setColor($color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @param string $chapo
     */
    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return string
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    /**
     * @param string $postscriptum
     */
    public function setPostscriptum($postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
