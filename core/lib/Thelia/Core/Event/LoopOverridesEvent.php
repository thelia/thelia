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


namespace Thelia\Core\Event;

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\Overrides\ArgDefinitionOverrideInterface;
use Thelia\Core\Template\Element\Overrides\ArrayBuilderOverrideInterface;
use Thelia\Core\Template\Element\Overrides\ParseOverrideInterface;
use Thelia\Core\Template\Element\Overrides\PropelBuilderOverrideInterface;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;

/**
 * Class LoopOverridesEvent
 * @package Thelia\Core\Event
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class LoopOverridesEvent extends ActionEvent
{
    /** @var BaseLoop|null  */
    protected $loop = null;

    protected $argDefinition = [];

    protected $argInitialization = [];

    protected $builder = [];

    protected $parser = [];

    public function __construct(BaseLoop $loop)
    {
        $this->loop = $loop;
    }

    /**
     * @return null|BaseLoop
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @param null|BaseLoop $loop
     */
    public function setLoop($loop)
    {
        $this->loop = $loop;
    }

    /**
     * @return array
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @return array
     */
    public function getArgDefinition()
    {
        return $this->argDefinition;
    }

    /**
     * @return array
     */
    public function getArgInitialization()
    {
        return $this->argInitialization;
    }

    /**
     * @return $this
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * @return $this
     */
    public function addBuilder($builder)
    {
        if ($this->loop instanceof PropelSearchLoopInterface) {
            if ($builder instanceof PropelBuilderOverrideInterface) {
                $this->builder[] = $builder;
            } else {
                throw new \InvalidArgumentException('builder should implements PropelBuilderOverrideInterface');
            }
        } elseif ($this->loop instanceof ArraySearchLoopInterface) {
            if ($builder instanceof ArrayBuilderOverrideInterface) {
                $this->builder[] = $builder;
            } else {
                throw new \InvalidArgumentException('builder should implements PropelBuilderOverrideInterface');
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addArgDefinition(ArgDefinitionOverrideInterface $argDefinition)
    {
        $this->argDefinition[] = $argDefinition;

        return $this;
    }

    /**
     * @return $this
     */
    public function addArgInitialization(ArgInitializationOverrideInterface $argInitialization)
    {
        $this->argInitialization[] = $argInitialization;

        return $this;
    }
    /**
     * @return $this
     */
    public function addParser(ParseOverrideInterface $parser)
    {
        $this->argParser[] = $parser;

        return $this;
    }
}
