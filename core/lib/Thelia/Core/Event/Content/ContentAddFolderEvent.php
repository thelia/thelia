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

namespace Thelia\Core\Event\Content;
use Thelia\Model\Content;

/**
 * Class ContentAddFolderEvent
 * @package Thelia\Core\Event\Content
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ContentAddFolderEvent extends ContentEvent
{

    /**
     * @var int folder id
     */
    protected $folderId;

    public function __construct(Content $content, $folderId)
    {
        $this->folderId = $folderId;

        parent::__construct($content);
    }

    /**
     * @param int $folderId
     */
    public function setFolderId($folderId)
    {
        $this->folderId = $folderId;
    }

    /**
     * @return int
     */
    public function getFolderId()
    {
        return $this->folderId;
    }

}
