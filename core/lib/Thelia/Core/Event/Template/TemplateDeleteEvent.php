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

class TemplateDeleteEvent extends TemplateEvent
{
    protected int $template_id;
    protected $product_count;

    public function __construct(int $template_id)
    {
        $this->setTemplateId($template_id);
    }

    public function getTemplateId(): int
    {
        return $this->template_id;
    }

    public function setTemplateId(int $template_id): static
    {
        $this->template_id = $template_id;

        return $this;
    }

    public function getProductCount()
    {
        return $this->product_count;
    }

    public function setProductCount($product_count): static
    {
        $this->product_count = $product_count;

        return $this;
    }
}
