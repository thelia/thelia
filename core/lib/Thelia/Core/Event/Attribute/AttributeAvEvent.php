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
