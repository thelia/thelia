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
class HookCreateEvent extends HookEvent
{
    /** @var string */
    protected $locale;

    /** @var string */
    protected $code;

    /** @var int */
    protected $type;

    /** @var string */
    protected $title;

    /** @var int */
    protected $native;

    /** @var int */
    protected $active;

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale): self
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
     * @return $this
     */
    public function setNative($native): self
    {
        $this->native = $native;

        return $this;
    }

    public function getNative()
    {
        return $this->native;
    }

    /**
     * @return $this
     */
    public function setTitle($title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return $this
     */
    public function setType($type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setActive($active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getActive()
    {
        return $this->active;
    }

    /**
     * @return $this
     */
    public function setCode($code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }
}
