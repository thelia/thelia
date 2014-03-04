<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Folder;

/**
 * Class FolderDeleteEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
