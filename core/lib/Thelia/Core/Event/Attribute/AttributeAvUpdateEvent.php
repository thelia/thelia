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

class AttributeAvUpdateEvent extends AttributeAvCreateEvent
{
    /** @var int */
    protected $attributeAv_id;

    protected $description;
    protected $chapo;
    protected $postscriptum;

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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
