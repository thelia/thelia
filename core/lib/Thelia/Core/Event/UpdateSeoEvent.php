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

namespace Thelia\Core\Event;

class UpdateSeoEvent extends ActionEvent
{
    protected $object_id;
    protected $locale;
    protected $url;
    protected $meta_title;
    protected $meta_description;
    protected $meta_keywords;

    protected $object;

    /**
     * @param $object_id
     * @param null $locale
     * @param null $url
     * @param null $meta_title
     * @param null $meta_description
     * @param null $meta_keywords
     */
    public function __construct($object_id, $locale = null,  $url = null, $meta_title = null, $meta_description = null, $meta_keywords = null)
    {
        $this->object_id = $object_id;
        $this->locale = $locale;
        $this->url = $url;
        $this->meta_title = $meta_title;
        $this->meta_description = $meta_description;
        $this->meta_keywords = $meta_keywords;
    }

    /**
     * @return mixed
     */
    public function getObjectId()
    {
        return $this->object_id;
    }

    /**
     * @param $object_id
     * @return $this
     */
    public function setObjectId($object_id)
    {
        $this->object_id = $object_id;

        return $this;
    }

    /**
     * @return null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param $locale
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return null
     */
    public function getMetaTitle()
    {
        return $this->meta_title;
    }

    /**
     * @param $meta_title
     * @return $this
     */
    public function setMetaTitle($meta_title)
    {
        $this->meta_title = $meta_title;

        return $this;
    }

    /**
     * @return null
     */
    public function getMetaDescription()
    {
        return $this->meta_description;
    }

    /**
     * @param $meta_description
     * @return $this
     */
    public function setMetaDescription($meta_description)
    {
        $this->meta_description = $meta_description;

        return $this;
    }

    /**
     * @return null
     */
    public function getMetaKeywords()
    {
        return $this->meta_keywords;
    }

    /**
     * @param $meta_keywords
     * @return $this
     */
    public function setMetaKeywords($meta_keywords)
    {
        $this->meta_keywords = $meta_keywords;

        return $this;
    }

    /**
     * @param mixed $object
     */
    public function setObject($object)
    {
        $this->object = $object;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }

}
