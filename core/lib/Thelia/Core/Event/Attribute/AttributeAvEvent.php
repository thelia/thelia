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

namespace Thelia\Core\Event\Attribute;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\AttributeAv;

class AttributeAvEvent extends ActionEvent
{
    protected $attributeAv = null;

    public function __construct(AttributeAv $attributeAv = null)
    {
        $this->attributeAv = $attributeAv;
    }

    public function hasAttributeAv()
    {
        return ! is_null($this->attributeAv);
    }

    public function getAttributeAv()
    {
        return $this->attributeAv;
    }

    public function setAttributeAv($attributeAv)
    {
        $this->attributeAv = $attributeAv;

        return $this;
    }
}
