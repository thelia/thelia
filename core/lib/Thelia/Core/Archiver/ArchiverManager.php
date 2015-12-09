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

namespace Thelia\Core\Archiver;

use Thelia\Core\Translation\Translator;

/**
 * Class ArchiverManager
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ArchiverManager
{
    /**
     * @var array List of handled archivers
     */
    protected $archivers = [];

    /**
     * Reset manager
     *
     * @return $this Return $this, allow chaining
     */
    public function reset()
    {
        $this->archivers = [];

        return $this;
    }

    /**
     * Get all archivers
     *
     * @return array
     */
    public function getArchivers()
    {
        return $this->archivers;
    }

    /**
     * Determine if an archiver exists under the given identifier
     *
     * @param string  $archiverId     An archiver identifier
     * @param boolean $throwException Throw exception if archiver doesn't exists or not
     *
     * @throws \InvalidArgumentException if the archiver identifier does not exist
     *
     * @return boolean True if the archiver exists, false otherwise
     */
    public function has($archiverId, $throwException = false)
    {
        $exists = isset($this->archivers[$archiverId]);

        if (!$exists && $throwException) {
            throw new \InvalidArgumentException(
                Translator::getInstance()->trans(
                    'The archiver identifier "%archiverId" doesn\’t exist',
                    [
                        '%archiverId' => $archiverId
                    ]
                )
            );
        }

        return $exists;
    }

    /**
     * Get an archiver
     *
     * @param string $archiverId An archiver identifier
     *
     * @return \Thelia\Core\Archiver\ArchiverInterface Return an archiver
     */
    public function get($archiverId)
    {
        $this->has($archiverId, true);

        return $this->archivers[$archiverId];
    }

    /**
     * Set archivers
     *
     * @param array $archivers An array of archiver
     *
     * @throws \Exception
     *
     * @return $this Return $this, allow chaining
     */
    public function setArchivers(array $archivers = [])
    {
        $this->archivers = [];

        foreach ($archivers as $archiver) {
            if (!($archiver instanceof ArchiverInterface)) {
                // Todo
                throw new \Exception('TODO: ' . __FILE__);
            }

            $this->archivers[$archiver->getId()] = $archiver;
        }

        return $this;
    }

    /**
     * Add an archiver
     *
     * @param \Thelia\Core\Archiver\ArchiverInterface $archiver An archiver
     *
     * @return $this Return $this, allow chaining
     */
    public function add(ArchiverInterface $archiver)
    {
        $this->archivers[$archiver->getId()] = $archiver;

        return $this;
    }

    /**
     * Remove an archiver
     *
     * @param string $archiverId An archiver identifier
     */
    public function remove($archiverId)
    {
        $this->has($archiverId, true);

        unset($this->archivers[$archiverId]);
    }
}
