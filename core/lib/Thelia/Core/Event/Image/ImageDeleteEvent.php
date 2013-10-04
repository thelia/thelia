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

namespace Thelia\Core\Event\Image;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderImage;
use Thelia\Model\ProductImage;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Occurring when a Image is about to be deleted
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ImageDeleteEvent extends ActionEvent
{
    /** @var string Image type */
    protected $imageType = null;

    /** @var CategoryImage|ProductImage|ContentImage|FolderImage Image about to be deleted */
    protected $imageToDelete = null;

    /**
     * Constructor
     *
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $imageToDelete Image about to be deleted
     * @param string                                              $imageType     Image type
     *                                                                         ex : FileManager::TYPE_CATEGORY
     */
    public function __construct($imageToDelete, $imageType)
    {
        $this->imageToDelete = $imageToDelete;
        $this->imageType = $imageType;
    }

    /**
     * Set picture type
     *
     * @param string $imageType Image type
     *
     * @return $this
     */
    public function setImageType($imageType)
    {
        $this->imageType = $imageType;

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
     * Set Image about to be deleted
     *
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $imageToDelete Image about to be deleted
     *
     * @return $this
     */
    public function setImageToDelete($imageToDelete)
    {
        $this->imageToDelete = $imageToDelete;

        return $this;
    }

    /**
     * Get Image about to be deleted
     *
     * @return CategoryImage|ProductImage|ContentImage|FolderImage
     */
    public function getImageToDelete()
    {
        return $this->imageToDelete;
    }

}
