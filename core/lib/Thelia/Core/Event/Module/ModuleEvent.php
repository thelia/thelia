<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Module;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Module;

/**
 * Class ModuleEvent.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Module
     */
    protected $module;

    protected $id;
    protected $locale;
    protected $title;
    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function setChapo($chapo): void
    {
        $this->chapo = $chapo;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description): void
    {
        $this->description = $description;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setLocale($locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setPostscriptum($postscriptum): void
    {
        $this->postscriptum = $postscriptum;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function __construct(Module $module = null)
    {
        $this->module = $module;
    }

    /**
     * @return $this
     */
    public function setModule(Module $module)
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return \Thelia\Model\Module
     */
    public function getModule()
    {
        return $this->module;
    }

    public function hasModule()
    {
        return null !== $this->module;
    }
}
