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

class AttributeDeleteEvent extends AttributeEvent
{
    protected int $attribute_id;

    public function __construct(int $attribute_id)
    {
        $this->setAttributeId($attribute_id);
    }

    public function getAttributeId(): int
    {
        return $this->attribute_id;
    }

    public function setAttributeId(int $attribute_id): static
    {
        $this->attribute_id = $attribute_id;

        return $this;
    }
}
