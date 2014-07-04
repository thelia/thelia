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

namespace Thelia\Core\FileFormat\Archive;
use Thelia\Core\Translation\Translator;

/**
 * Class ArchiveBuilderManager
 * @package Thelia\Core\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ArchiveBuilderManager
{
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Add getNames methods to managers
    /** @var array */
    protected $archiveBuilders = array();
=======
    protected $archiveCreators = array();
>>>>>>> Define archive builders and formatters
=======
    protected $archiveBuilders = array();
>>>>>>> Fix cs and add get method in managers

    protected $environment;

    public function __construct($environment)
    {
        $this->environment = $environment;
    }
    /**
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
     * @param  AbstractArchiveBuilder $archiveBuilder
     * @return $this
     */
    public function add(AbstractArchiveBuilder $archiveBuilder)
    {
        if (null !== $archiveBuilder) {
            $archiveBuilder->setEnvironment($this->environment);

            $this->archiveBuilders[$archiveBuilder->getName()] = $archiveBuilder;
=======
     * @param AbstractArchiveBuilder $archiveCreator
=======
     * @param  AbstractArchiveBuilder $archiveCreator
>>>>>>> Fix cs
=======
     * @param  AbstractArchiveBuilder $archiveBuilder
>>>>>>> Add getNames methods to managers
     * @return $this
     */
    public function add(AbstractArchiveBuilder $archiveBuilder)
    {
        if (null !== $archiveBuilder) {
            $archiveBuilder->setEnvironment($this->environment);

<<<<<<< HEAD
<<<<<<< HEAD
            $this->archiveCreators[$archiveCreator->getName()] = $archiveCreator;
>>>>>>> Define archive builders and formatters
=======
            $this->archiveBuilders[$archiveCreator->getName()] = $archiveCreator;
>>>>>>> Fix cs and add get method in managers
=======
            $this->archiveBuilders[$archiveBuilder->getName()] = $archiveBuilder;
>>>>>>> Add getNames methods to managers
        }

        return $this;
    }

    /**
     * @param $name
     * @return $this
     * @throws \OutOfBoundsException
     */
    public function delete($name)
    {
<<<<<<< HEAD
<<<<<<< HEAD
        if (!array_key_exists($name, $this->archiveBuilders)) {
            $this->throwOutOfBounds($name);
        }

        unset($this->archiveBuilders[$name]);
=======
        if (!array_key_exists($name, $this->archiveCreators)) {
            throw new \OutOfBoundsException(
                Translator::getInstance()->trans(
                    "The archive creator %name doesn't exist",
                    [
                        "%name" => $name
                    ]
                )
            );
        }

        unset($this->archiveCreators[$name]);
>>>>>>> Define archive builders and formatters
=======
        if (!array_key_exists($name, $this->archiveBuilders)) {
            $this->throwOutOfBounds($name);
        }

        unset($this->archiveBuilders[$name]);
>>>>>>> Fix cs and add get method in managers

        return $this;
    }

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @return array
     */
    public function getAll()
    {
        return $this->archiveBuilders;
    }

    /**
     * @return array
     */
    public function getNames()
    {
        $names = [];

        /** @var AbstractArchiveBuilder $builder */
        foreach($this->archiveBuilders as $builder) {
            $names[] = $builder->getName();
        }

        return $names;
    }

    public function get($name)
    {
        if (!array_key_exists($name, $this->archiveBuilders)) {
            $this->throwOutOfBounds($name);
        }

        return $this->archiveBuilders[$name];
    }

    protected function throwOutOfBounds($name)
    {
        throw new \OutOfBoundsException(
            Translator::getInstance()->trans(
                "The archive creator %name doesn't exist",
                [
                    "%name" => $name
                ]
            )
        );
    }
}
=======
     * @return array[AbstractArchiveBuilder]
=======
     * @return array
>>>>>>> Add getNames methods to managers
     */
    public function getAll()
    {
        return $this->archiveBuilders;
    }

    /**
     * @return array
     */
    public function getNames()
    {
        $names = [];

        /** @var AbstractArchiveBuilder $builder */
        foreach($this->archiveBuilders as $builder) {
            $names[] = $builder->getName();
        }

        return $names;
    }

    public function get($name)
    {
        if (!array_key_exists($name, $this->archiveBuilders)) {
            $this->throwOutOfBounds($name);
        }

        return $this->archiveBuilders[$name];
    }

    protected function throwOutOfBounds($name)
    {
        throw new \OutOfBoundsException(
            Translator::getInstance()->trans(
                "The archive creator %name doesn't exist",
                [
                    "%name" => $name
                ]
            )
        );
    }
<<<<<<< HEAD
} 
>>>>>>> Define archive builders and formatters
=======
}
>>>>>>> Fix cs
