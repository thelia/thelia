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

class AttributeDeleteEvent extends AttributeEvent
{
    /** @var int */
    protected $attribute_id;

    /**
     * @param int $attribute_id
     */
    public function __construct($attribute_id)
    {
        $this->setAttributeId($attribute_id);
    }

    public function getAttributeId()
    {
        return $this->attribute_id;
    }

    public function setAttributeId($attribute_id)
    {
        $this->attribute_id = $attribute_id;

        return $this;
    }
}
