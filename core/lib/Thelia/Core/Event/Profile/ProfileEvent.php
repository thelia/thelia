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

namespace Thelia\Core\Event\Profile;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Profile;

class ProfileEvent extends ActionEvent
{
    protected $profile = null;
    protected $id = null;
    protected $locale = null;
    protected $code = null;
    protected $title = null;
    protected $chapo = null;
    protected $description = null;
    protected $postscriptum = null;
    protected $resourceAccess = null;
    protected $moduleAccess = null;

    public function __construct(Profile $profile = null)
    {
        $this->profile = $profile;
    }

    public function hasProfile()
    {
        return ! is_null($this->profile);
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile)
    {
        $this->profile = $profile;

        return $this;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setResourceAccess($resourceAccess)
    {
        $this->resourceAccess = $resourceAccess;

        return $this;
    }

    public function getResourceAccess()
    {
        return $this->resourceAccess;
    }

    public function setModuleAccess($moduleAccess)
    {
        $this->moduleAccess = $moduleAccess;

        return $this;
    }

    public function getModuleAccess()
    {
        return $this->moduleAccess;
    }
}
