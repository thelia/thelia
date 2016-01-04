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
     * Get all archivers or only those match availability
     *
     * @param null|boolean $isAvailable Filter archivers by availability
     *
     * @return array All, or filtered by availability, archivers
     */
    public function getArchivers($isAvailable = null)
    {
        if ($isAvailable === null) {
            return $this->archivers;
        }

        $filteredArchivers = [];

        /** @var \Thelia\Core\Archiver\ArchiverInterface $archiver */
        foreach ($this->archivers as $archiver) {
            if ($archiver->isAvailable() === (bool) $isAvailable) {
                $filteredArchivers[] = $archiver;
            }
        }

        return $filteredArchivers;
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
     * @param string       $archiverId  An archiver identifier
     * @param null|boolean $isAvailable Filter archiver by availability
     *
     * @return null|\Thelia\Core\Archiver\ArchiverInterface Return an archiver or null depends on availability
     */
    public function get($archiverId, $isAvailable = null)
    {
        $this->has($archiverId, true);

        if ($isAvailable === null) {
            return $this->archivers[$archiverId];
        }

        if ($this->archivers[$archiverId]->isAvailable() === (bool) $isAvailable) {
            return $this->archivers[$archiverId];
        }

        return null;
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
    public function setArchivers(array $archivers)
    {
        $this->archivers = [];

        foreach ($archivers as $archiver) {
            if (!($archiver instanceof ArchiverInterface)) {
                throw new \Exception('ArchiverManager manage only ' . __NAMESPACE__ . '\\ArchiverInterface');
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
