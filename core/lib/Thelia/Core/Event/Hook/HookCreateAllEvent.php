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
namespace Thelia\Core\Event\Hook;

/**
 * Class HookCreateEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookCreateAllEvent extends HookEvent
{
    /** @var string */
    protected $locale;

    /** @var string */
    protected $code;

    /** @var int */
    protected $type;

    /** @var bool */
    protected $native;

    /** @var bool */
    protected $active;

    /** @var bool */
    protected $by_module;

    /** @var bool */
    protected $block;

    /** @var string */
    protected $title;

    /** @var string */
    protected $chapo;

    /** @var string */
    protected $description;

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

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
     * @param bool $native
     *
     * @return $this
     */
    public function setNative($native): static
    {
        $this->native = $native;

        return $this;
    }

    /**
     * @return bool
     */
    public function getNative()
    {
        return $this->native;
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title): static
    {
        $this->title = $title;

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
     * @param int $type
     *
     * @return $this
     */
    public function setType($type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param bool $active
     *
     * @return $this
     */
    public function setActive($active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param string $code
     *
     * @return $this
     */
    public function setCode($code): static
    {
        $this->code = $code;

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
     * @param bool $block
     *
     * @return $this
     */
    public function setBlock($block): static
    {
        $this->block = $block;

        return $this;
    }

    /**
     * @return bool
     */
    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @param bool $by_module
     *
     * @return $this
     */
    public function setByModule($by_module): static
    {
        $this->by_module = $by_module;

        return $this;
    }

    /**
     * @return bool
     */
    public function getByModule()
    {
        return $this->by_module;
    }

    /**
     * @param string $chapo
     *
     * @return $this
     */
    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

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
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
