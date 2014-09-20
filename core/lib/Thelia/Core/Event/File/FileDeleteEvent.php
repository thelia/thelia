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

namespace Thelia\Core\Event\File;

use Thelia\Core\Event\ActionEvent;
use Thelia\Files\FileModelInterface;

/**
 * Event fired when a file is about to be deleted
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class FileDeleteEvent extends ActionEvent
{
    /** @var FileModelInterface Image about to be deleted */
    protected $fileToDelete = null;

    /**
     * Constructor
     *
     * @param FileModelInterface $fileToDelete Image about to be deleted
     */
    public function __construct($fileToDelete)
    {
        $this->fileToDelete = $fileToDelete;
    }

    /**
     * Set Image about to be deleted
     *
     * @param FileModelInterface $fileToDelete Image about to be deleted
     *
     * @return $this
     */
    public function setFileToDelete($fileToDelete)
    {
        $this->fileToDelete = $fileToDelete;

        return $this;
    }

    /**
     * Get Image about to be deleted
     *
     * @return FileModelInterface
     */
    public function getFileToDelete()
    {
        return $this->fileToDelete;
    }
}
