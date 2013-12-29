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
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Folder;

/**
 * Class FolderEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
