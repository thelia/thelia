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
use Thelia\Model\Attribute;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\AttributeEvent
 */
class AttributeEvent extends ActionEvent
{
    public function __construct(protected ?Attribute $attribute = null)
    {
    }

    public function hasAttribute(): bool
    {
        return $this->attribute instanceof Attribute;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
