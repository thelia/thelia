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

namespace Thelia\Core\Event\Image;

use Thelia\Core\Event\File\FileDeleteEvent;
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
 * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
 */
class ImageDeleteEvent extends FileDeleteEvent
{
    /**
     * Constructor
     *
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $imageToDelete Image about to be deleted
     * @param string                                              $imageType     Image type
     *                                                                           ex : FileManager::TYPE_CATEGORY
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function __construct($imageToDelete, $imageType)
    {
        parent::__construct($imageToDelete);
    }

    /**
     * Set picture type
     *
     * @param string $imageType Image type
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function setImageType($imageType)
    {
        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function getImageType()
    {
        throw new \RuntimeException("getImageType() is deprecated and no longer supported");
    }

    /**
     * Set Image about to be deleted
     *
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $imageToDelete Image about to be deleted
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function setImageToDelete($imageToDelete)
    {
        parent::setFileToDelete($imageToDelete);

        return $this;
    }

    /**
     * Get Image about to be deleted
     *
     * @return CategoryImage|ProductImage|ContentImage|FolderImage
     * @deprecated deprecated since version 2.0.3. Use FileDeleteEvent instead
     */
    public function getImageToDelete()
    {
        return parent::getFileToDelete();
    }
}
