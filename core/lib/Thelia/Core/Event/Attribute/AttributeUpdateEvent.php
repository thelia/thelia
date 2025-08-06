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

class AttributeUpdateEvent extends AttributeCreateEvent
{
    protected int $attribute_id;
    protected $description;
    protected $chapo;
    protected $postscriptum;

    public function __construct(int $attributeId)
    {
        $this->setAttributeId($attributeId);
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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
