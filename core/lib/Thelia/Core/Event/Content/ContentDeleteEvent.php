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

/**
 * Class ContentDeleteEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ContentDeleteEvent extends ContentEvent
{
    protected $content_id;

    protected $folder_id;

    public function __construct($content_id)
    {
        $this->content_id = $content_id;
    }

    /**
     * @param mixed $content_id
     *
     * @return $this
     */
    public function setContentId($content_id)
    {
        $this->content_id = $content_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContentId()
    {
        return $this->content_id;
    }

    public function setDefaultFolderId($folderid)
    {
        $this->folder_id = $folderid;
    }

    public function getDefaultFolderId()
    {
        return $this->folder_id;
    }

}
