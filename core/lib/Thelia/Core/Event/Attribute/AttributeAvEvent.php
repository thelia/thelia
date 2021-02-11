<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Attribute;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\AttributeAv;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\AttributeAvEvent
 */
class AttributeAvEvent extends ActionEvent
{
    protected $attributeAv;

    public function __construct(AttributeAv $attributeAv = null)
    {
        $this->attributeAv = $attributeAv;
    }

    public function hasAttributeAv()
    {
        return ! \is_null($this->attributeAv);
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
