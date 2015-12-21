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

namespace Thelia\Core\Event\Folder;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Folder;

/**
 * Class FolderEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Folder
     */
    protected $folder;

    public function __construct(Folder $folder = null)
    {
        $this->folder = $folder;
    }

    /**
     * @param \Thelia\Model\Folder $folder
     */
    public function setFolder(Folder $folder)
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @return \Thelia\Model\Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * test if a folder object exists
     *
     * @return bool
     */
    public function hasFolder()
    {
        return null !== $this->folder;
    }
}
