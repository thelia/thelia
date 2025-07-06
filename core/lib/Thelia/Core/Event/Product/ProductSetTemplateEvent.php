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

namespace Thelia\Core\Event\Product;

use Thelia\Model\Product;

class ProductSetTemplateEvent extends ProductEvent
{
    public function __construct(?Product $product = null, protected $template_id = null, protected $currency_id = null)
    {
        parent::__construct($product);
    }

    public function getTemplateId()
    {
        return $this->template_id;
    }

    public function setTemplateId($template_id): static
    {
        $this->template_id = $template_id;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id): static
    {
        $this->currency_id = $currency_id;

        return $this;
    }
}
