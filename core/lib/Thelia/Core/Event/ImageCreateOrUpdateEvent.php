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

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Occurring when a Image list is saved
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ImageCreateOrUpdateEvent extends ActionEvent
{
    CONST TYPE_PRODUCT  = 'product';
    CONST TYPE_CATEGORY = 'category';
    CONST TYPE_CONTENT  = 'content';
    CONST TYPE_FOLDER   = 'folder';

    /** @var array Images model to save */
    protected $modelImages = array();

    /** @var array Images file to save */
    protected $uploadedFiles = array();

    /** @var int Image parent id */
    protected $parentId = null;

    /** @var string Image type */
    protected $imageType = null;

    /** @var array Available image parent type */
    protected static $availableType = array(
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
        self::TYPE_CONTENT,
        self::TYPE_FOLDER,
    );

    /**
     * Constructor
     *
     * @param string $pictureType Picture type
     *                            ex : ImageCreateOrUpdateEvent::TYPE_CATEGORY
     * @param int    $parentId    Image parent id
     */
    public function __construct($pictureType, $parentId)
    {
        $this->imageType = $pictureType;
        $this->parentId  = $parentId;
    }

    /**
     * Set Images to save
     *
     * @param array $images Thelia\Model\CategoryImage Array
     *
     * @return $this
     */
    public function setModelImages($images)
    {
        $this->modelImages = $images;

        return $this;
    }

    /**
     * Get Images being saved
     *
     * @return array Array of Thelia\Model\CategoryImage
     */
    public function getModelImages()
    {
        return $this->modelImages;
    }

    /**
     * Set Images to save
     *
     * @param array $images Thelia\Model\CategoryImage Array
     *
     * @return $this
     */
    public function setUploadedFiles($images)
    {
        $this->uploadedFiles = $images;

        return $this;
    }

    /**
     * Get Images being saved
     *
     * @return array Array of Thelia\Model\CategoryImage
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * Set picture type
     *
     * @param string $pictureType Picture type
     *
     * @return $this
     */
    public function setImageType($pictureType)
    {
        $this->imageType = $pictureType;

        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     */
    public function getImageType()
    {
        return $this->imageType;
    }

    /**
     * Get all image parent type available
     *
     * @return array
     */
    public static function getAvailableType()
    {
        return self::$availableType;
    }

    /**
     * Set Image parent id
     *
     * @param int $parentId Image parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get Image parent id
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parentId;
    }


}
