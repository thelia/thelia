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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\AttributeAv;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\AttributeAvEvent
 */
class AttributeAvEvent extends ActionEvent
{
    public function __construct(protected ?AttributeAv $attributeAv = null)
    {
    }

    public function hasAttributeAv(): bool
    {
        return $this->attributeAv instanceof AttributeAv;
    }

    public function getAttributeAv(): ?AttributeAv
    {
        return $this->attributeAv;
    }

    public function setAttributeAv(?AttributeAv $attributeAv): static
    {
        $this->attributeAv = $attributeAv;

        return $this;
    }
}
