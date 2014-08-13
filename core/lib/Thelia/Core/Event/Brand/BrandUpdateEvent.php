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

namespace Thelia\Core\Event\Brand;

/**
 * Class BrandUpdateEvent
 * @package Thelia\Core\Event\Brand
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandUpdateEvent extends BrandCreateEvent
{
    protected $brandId;

    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $logo_image_id;

    /**
     * @param int $brandId
     */
    public function __construct($brandId)
    {
        $this->brandId = $brandId;
    }

    /**
     * @param string $chapo
     *
     * @return BrandUpdateEvent $this
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return string
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @param int $brandId
     *
     * @return BrandUpdateEvent $this
     */
    public function setBrandId($brandId)
    {
        $this->brandId = $brandId;

        return $this;
    }

    /**
     * @return int
     */
    public function getBrandId()
    {
        return $this->brandId;
    }

    /**
     * @param string $description
     *
     * @return BrandUpdateEvent $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $postscriptum
     *
     * @return BrandUpdateEvent $this
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return string
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    /**
     * @param  int   $logo_image_id
     * @return $this
     */
    public function setLogoImageId($logo_image_id)
    {
        $this->logo_image_id = $logo_image_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getLogoImageId()
    {
        return $this->logo_image_id;
    }
}
