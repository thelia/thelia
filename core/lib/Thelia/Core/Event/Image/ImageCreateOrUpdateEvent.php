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

use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Files\FileModelInterface;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM
 *
 * Occurring when an Image is saved
 *
 * @package Image
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
 */
class ImageCreateOrUpdateEvent extends FileCreateOrUpdateEvent
{
    /**
     * Constructor
     *
     * @param string $imageType Image type
     *                          ex : FileManager::TYPE_CATEGORY
     * @param int    $parentId  Image parent id
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function __construct($imageType, $parentId)
    {
        parent::__construct($parentId);
    }

    /**
     * @param mixed $locale
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setLocale($locale)
    {
        return $this;
    }

    /**
     * @return mixed
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getLocale()
    {
        throw new \RuntimeException("getLocale() is deprecated and no longer supported");
    }

    /**
     * Set Image to save
     *
     * @param $image FileModelInterface
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setModelImage($image)
    {
        parent::setModel($image);
    }

    /**
     * Get Image being saved
     *
     * @return FileModelInterface
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getModelImage()
    {
        return parent::getModel();
    }

    /**
     * Set picture type
     *
     * @param string $imageType Image type
     *
     * @return $this
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setImageType($imageType)
    {
        return $this;
    }

    /**
     * Get picture type
     *
     * @return string
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getImageType()
    {
        throw new \RuntimeException("getImageType() is deprecated and no longer supported");
    }

    /**
     * Set old model value
     *
     * @param FileModelInterface $oldModelImage
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function setOldModelImage($oldModelImage)
    {
        parent::setOldModel($oldModelImage);
    }

    /**
     * Get old model value
     *
     * @return FileModelInterface
     * @deprecated deprecated since version 2.0.3. Use FileCreateOrUpdateEvent instead
     */
    public function getOldModelImage()
    {
        return parent::getOldModel();
    }
}
