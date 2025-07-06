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
namespace Thelia\Core\Event\Template;

use Thelia\Model\Template;

class TemplateDeleteAttributeEvent extends TemplateEvent
{
    public function __construct(Template $template, protected $attribute_id)
    {
        parent::__construct($template);
    }

    public function getAttributeId()
    {
        return $this->attribute_id;
    }

    public function setAttributeId($attribute_id): static
    {
        $this->attribute_id = $attribute_id;

        return $this;
    }
}
