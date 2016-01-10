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

class AttributeAvDeleteEvent extends AttributeAvEvent
{
    /** @var int */
    protected $attributeAv_id;

    /**
     * @param int $attributeAv_id
     */
    public function __construct($attributeAv_id)
    {
        $this->setAttributeAvId($attributeAv_id);
    }

    public function getAttributeAvId()
    {
        return $this->attributeAv_id;
    }

    public function setAttributeAvId($attributeAv_id)
    {
        $this->attributeAv_id = $attributeAv_id;

        return $this;
    }
}
