<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Hook;

/**
 * Class HookCreateEvent
 * @package Thelia\Core\Event\Hook
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
     * @return $this
     */
    public function setLocale($locale)
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
     * @return $this
     */
    public function setNative($native)
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
     * @return $this
     */
    public function setTitle($title)
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
     * @return $this
     */
    public function setType($type)
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
     * @return $this
     */
    public function setActive($active)
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
     * @return $this
     */
    public function setCode($code)
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
     * @return $this
     */
    public function setBlock($block)
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
     * @return $this
     */
    public function setByModule($by_module)
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
     * @return $this
     */
    public function setChapo($chapo)
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
     * @return $this
     */
    public function setDescription($description)
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
