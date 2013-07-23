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

namespace Thelia\Log;

use Thelia\Model\Config;
use Thelia\Model\ConfigDesc;
use Thelia\Model\ConfigQuery;

class TlogDestinationConfig
{

    const TYPE_TEXTAREA = 1;
    const TYPE_TEXTFIELD = 2;

    public $name;
    public $title;
    public $label;
    public $default;
    public $type;
    public $value;

    public function __construct($name, $title, $label, $default, $type)
    {
        $this->name = $name;
        $this->title = $title;
        $this->label = $label;
        $this->default = $default;
        $this->type = $type;

        $this->load();
    }

    
    public function load()
    {
        if (null === $config = ConfigQuery::create()->findOneByName($this->name))
        {
            $config = new Config();
            $config->setName($this->name);
            $config->setValue($this->default);
            $config->setHidden(1);
            $config->setSecured(1);
            $config->save();
        }
        
        $this->value = $config->getValue();
    }
}
