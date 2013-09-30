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

use Thelia\Core\Event\CachedFileEvent;

class ImageEvent extends CachedFileEvent
{
    /**
     * @var string The absolute path of the cached image file
     */
    protected $cache_filepath = null;

    /**
     * @var string The absolute URL of the cached version of the original image (in the web space)
     */
    protected $original_file_url = null;

    /**
     * @var string The absolute path of the cached version of the original image file
     */
    protected $cache_original_filepath = null;

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
     * @return boolean true if the required image is the original image (resize_mode and background_color are not significant)
     */
    public function isOriginalImage()
    {
        return empty($this->width) && empty($this->height) /* && empty($this->resize_mode) && empty($this->background_color) not significant */
                && empty($this->effects) && empty($this->rotation) && empty($this->quality);
    }

    /**
     * @return string a hash identifiying the processing options
     */
    public function getOptionsHash()
    {
        return md5(
                $this->width . $this->height . $this->resize_mode . $this->background_color . implode(',', $this->effects)
                        . $this->rotation);
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setWidth($width)
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    public function getResizeMode()
    {
        return $this->resize_mode;
    }

    public function setResizeMode($resize_mode)
    {
        $this->resize_mode = $resize_mode;

        return $this;
    }

    public function getBackgroundColor()
    {
        return $this->background_color;
    }

    public function setBackgroundColor($background_color)
    {
        $this->background_color = $background_color;

        return $this;
    }

    public function getEffects()
    {
        return $this->effects;
    }

    public function setEffects(array $effects)
    {
        $this->effects = $effects;

        return $this;
    }

    public function getRotation()
    {
        return $this->rotation;
    }

    public function setRotation($rotation)
    {
        $this->rotation = $rotation;

        return $this;
    }

    public function getQuality()
    {
        return $this->quality;
    }

    public function setQuality($quality)
    {
        $this->quality = $quality;

        return $this;
    }

    public function getOriginalFileUrl()
    {
        return $this->original_file_url;
    }

    public function setOriginalFileUrl($original_file_url)
    {
        $this->original_file_url = $original_file_url;

        return $this;
    }

    public function getCacheOriginalFilepath()
    {
        return $this->cache_original_filepath;
    }

    public function setCacheOriginalFilepath($cache_original_filepath)
    {
        $this->cache_original_filepath = $cache_original_filepath;

        return $this;
    }
}
