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

namespace TheliaSmarty\Events;

use Thelia\Core\Event\ActionEvent;

class ComponentRenderEvent extends ActionEvent
{
    public const COMPONENT_BEFORE_RENDER_PREFIX = 'component_before_render_';
    public const COMPONENT_AFTER_RENDER_PREFIX = 'component_after_render_';

    /** @var string */
    protected $render;

    /** @var string */
    protected $content;

    /** @var string */
    protected $name;

    /** @var string */
    protected $id;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string
     *
     * @return ComponentRenderEvent
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string
     *
     * @return ComponentRenderEvent
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return $this->render;
    }

    /**
     * @param string
     *
     * @return ComponentRenderEvent
     */
    public function setRender($render)
    {
        $this->render = $render;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string
     *
     * @return ComponentRenderEvent
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }
}
