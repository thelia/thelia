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
use Thelia\Core\Template\Element\Overrides\ArgDefinitionsOverrideInterface;
use Thelia\Core\Template\Element\Overrides\ArrayBuilderOverrideInterface;
use Thelia\Core\Template\Element\Overrides\InitializeArgsOverrideInterface;
use Thelia\Core\Template\Element\Overrides\ParseOverrideInterface;
use Thelia\Core\Template\Element\Overrides\ParseResultsOverrideInterface;
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

    protected $argDefinitions = [];

    protected $initializeArgs = [];

    protected $builder = [];

    protected $parserResults = [];

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
     * @return null|string
     */
    public function getLoopName()
    {
        return $this->loop->getLoopName();
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
    public function getArgDefinitions()
    {
        return $this->argDefinitions;
    }

    /**
     * @return array
     */
    public function getInitializeArgs()
    {
        return $this->initializeArgs;
    }

    /**
     * @return $this
     */
    public function getParserResults()
    {
        return $this->parserResults;
    }

    public function addClass($class)
    {
        if ($class instanceof ArgDefinitionsOverrideInterface) {
            $this->addArgDefinitions($class);
        }
        if ($class instanceof InitializeArgsOverrideInterface) {
            $this->addInitializeArgs($class);
        }
        if ($class instanceof PropelSearchLoopInterface || $class instanceof ArrayBuilderOverrideInterface) {
            $this->addBuilder($class);
        }
        if ($class instanceof ParseResultsOverrideInterface) {
            $this->addParserResults($class);
        }
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
                throw new \InvalidArgumentException('builder should implements ArraySearchLoopInterface');
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addArgDefinitions(ArgDefinitionsOverrideInterface $argDefinitions)
    {
        $this->argDefinitions[] = $argDefinitions;

        return $this;
    }

    /**
     * @return $this
     */
    public function addInitializeArgs(InitializeArgsOverrideInterface $initializeArgs)
    {
        $this->initializeArgs[] = $initializeArgs;

        return $this;
    }

    /**
     * @return $this
     */
    public function addParserResults(ParseResultsOverrideInterface $parserResults)
    {
        $this->parserResults[] = $parserResults;

        return $this;
    }
}
