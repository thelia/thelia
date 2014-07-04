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
    protected $archiveBuilders = array();

    protected $environment;

    public function __construct($environment)
    {
        $this->environment = $environment;
    }
    /**
     * @param  AbstractArchiveBuilder $archiveCreator
     * @return $this
     */
    public function add(AbstractArchiveBuilder $archiveCreator)
    {
        if (null !== $archiveCreator) {
            $archiveCreator->setEnvironment($this->environment);

            $this->archiveBuilders[$archiveCreator->getName()] = $archiveCreator;
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
        if (!array_key_exists($name, $this->archiveBuilders)) {
            $this->throwOutOfBounds($name);
        }

        unset($this->archiveBuilders[$name]);

        return $this;
    }

    /**
     * @return array[AbstractArchiveBuilder]
     */
    public function getAll()
    {
        return $this->archiveBuilders;
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
