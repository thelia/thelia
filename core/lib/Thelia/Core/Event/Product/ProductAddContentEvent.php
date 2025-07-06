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

class ProductAddContentEvent extends ProductEvent
{
    public function __construct(Product $product, protected $content_id)
    {
        parent::__construct($product);
    }

    public function getContentId()
    {
        return $this->content_id;
    }

    public function setContentId($content_id): void
    {
        $this->content_id = $content_id;
    }
}
