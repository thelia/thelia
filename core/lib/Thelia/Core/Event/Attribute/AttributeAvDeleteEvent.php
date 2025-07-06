<?php

declare(strict_types=1);

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

    public function setAttributeAvId($attributeAv_id): static
    {
        $this->attributeAv_id = $attributeAv_id;

        return $this;
    }
}
