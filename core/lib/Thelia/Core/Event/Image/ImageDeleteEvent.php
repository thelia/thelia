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

use Thelia\Core\Event\ActionEvent;
use Thelia\Files\FileModelInterface;

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

    /** @var FileModelInterface Image about to be deleted */
    protected $imageToDelete = null;

    /**
     * Constructor
     *
     * @param FileModelInterface $imageToDelete Image about to be deleted
     */
    public function __construct($imageToDelete)
    {
        $this->imageToDelete = $imageToDelete;
    }

    /**
     * Set Image about to be deleted
     *
     * @param FileModelInterface $imageToDelete Image about to be deleted
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
     * @return FileModelInterface
     */
    public function getImageToDelete()
    {
        return $this->imageToDelete;
    }

}
