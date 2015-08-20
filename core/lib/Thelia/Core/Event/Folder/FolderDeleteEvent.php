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

/**
 * Class FolderDeleteEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderDeleteEvent extends FolderEvent
{
    /**
     * @var int folder id
     */
    protected $folder_id;

    /**
     * @param int $folder_id
     */
    public function __construct($folder_id)
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @param int $folder_id
     */
    public function setFolderId($folder_id)
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @return int
     */
    public function getFolderId()
    {
        return $this->folder_id;
    }
}
