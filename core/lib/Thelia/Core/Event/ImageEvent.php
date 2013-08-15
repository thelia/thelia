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
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Cart;

class ImageEvent extends ActionEvent
{
    /**
     * @var string The complete file name (with path) of the source image
     */
    protected $source_filepath = null;
    /**
     * @var string The target subdirectory in the image cache
     */
    protected $cache_subdirectory = null;

    /**
     * @var string The absolute URL of the cached image (in the web space)
     */
    protected $file_url = null;

    /**
     * @var string The absolute path of the cached image file
     */
    protected $cache_filepath = null;

    /**
     *  @var string The image category (i.e. the subdirectory in image cache)
     */
    protected $category = null;

    /**
     * @var integer the required image width
     */
    protected $width = null;

    /**
     * @var int the required image height
     */
    protected $height = null;

    /**
     * @var string the resize mode, either crop, bands, none
     */
    protected $resize_mode = null;

    /**
     * @var string the background color in RGB format (eg. #ff8000)
     */
    protected $background_color = null;

    /**
     * @var array a list of effects (grayscale, negative, mirror...), applied in the specified order.
     */
    protected $effects = array();

    /**
     * @var int the rotation angle in degrees, none if zero or null
     */
    protected $rotation = null;

    /**
     * @var int the quality of the result image, from 0 (!) to 100
     */
    protected $quality = null;

    /**
     * @return boolean true if the required image is the original image
     */
    public function isOriginalImage() {
        return
            empty($this->width)
            && empty($this->height)
            && empty($this->resize_mode)
            && empty($this->background_color)
            && empty($this->effects)
            && empty($this->rotation)
        ;
    }

    /**
     * @return string a hash identifiying the processing options
     */
    public function getSignature() {
        return md5(
              $this->width
            . $this->height
            . $this->resize_mode
            . $this->background_color
            . implode(',', $this->effects)
            . $this->rotation
         );
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category)
    {
        $this->category = $category;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width)
    {
        $this->width = $width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = $height;
    }

    public function getResizeMode()
    {
        return $this->resize_mode;
    }

    public function setResizeMode($resize_mode)
    {
        $this->resize_mode = $resize_mode;
    }

    public function getBackgroundColor()
    {
        return $this->background_color;
    }

    public function setBackgroundColor($background_color)
    {
        $this->background_color = $background_color;
    }

    public function getEffects()
    {
        return $this->effects;
    }

    public function setEffects(array $effects)
    {
        $this->effects = $effects;
    }

    public function getRotation()
    {
        return $this->rotation;
    }

    public function setRotation($rotation)
    {
        $this->rotation = $rotation;
    }

    public function getFileUrl()
    {
        return $this->file_url;
    }

    public function setFileUrl($file_url)
    {
        $this->file_url = $file_url;
    }

    public function getCacheFilepath()
    {
        return $this->cache_filepath;
    }

    public function setCacheFilepath($cache_filepath)
    {
        $this->cache_filepath = $cache_filepath;
    }

    public function getSourceFilepath()
    {
        return $this->source_filepath;
    }

    public function setSourceFilepath($source_filepath)
    {
        $this->source_filepath = $source_filepath;
    }

    public function getCacheSubdirectory()
    {
        return $this->cache_subdirectory;
    }

    public function setCacheSubdirectory($cache_subdirectory)
    {
        $this->cache_subdirectory = $cache_subdirectory;
    }

    public function getQuality()
    {
        return $this->quality;
    }

    public function setQuality($quality)
    {
        $this->quality = $quality;
    }

}
