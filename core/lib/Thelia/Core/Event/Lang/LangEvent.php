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

namespace Thelia\Core\Event\Lang;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Lang;

/**
 * Class LangEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Lang
     */
    protected $lang;

    public function __construct(Lang $lang = null)
    {
        $this->lang = $lang;
    }

    /**
     * @param \Thelia\Model\Lang $lang
     */
    public function setLang(Lang $lang)
    {
        $this->lang = $lang;
    }

    /**
     * @return \Thelia\Model\Lang
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     *
     * check if lang object is present
     *
     * @return bool
     */
    public function hasLang()
    {
        return null !== $this->lang;
    }

}
