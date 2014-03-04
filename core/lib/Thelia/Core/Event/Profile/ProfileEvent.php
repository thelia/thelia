<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
