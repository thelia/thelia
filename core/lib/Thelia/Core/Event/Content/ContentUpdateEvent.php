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
 * Class ContentUpdateEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ContentUpdateEvent extends ContentCreateEvent
{
    protected $content_id;

    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function __construct($content_id)
    {
        $this->content_id = $content_id;
    }

    /**
     * @param mixed $chapo
     *
     * @return $this
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChapo()
    {
        return $this->chapo;
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

    /**
     * @param mixed $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $postscriptum
     *
     * @return $this
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

}
