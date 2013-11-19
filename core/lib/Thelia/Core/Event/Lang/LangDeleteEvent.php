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

/**
 * Class LangDeleteEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class LangDeleteEvent extends LangEvent
{
    /**
     * @var int
     */
    protected $lang_id;

    /**
     * @param int $lang_id
     */
    public function __construct($lang_id)
    {
        $this->lang_id = $lang_id;
    }

    /**
     * @param int $lang_id
     *
     * @return $this
     */
    public function setLangId($lang_id)
    {
        $this->lang_id = $lang_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getLangId()
    {
        return $this->lang_id;
    }

}
