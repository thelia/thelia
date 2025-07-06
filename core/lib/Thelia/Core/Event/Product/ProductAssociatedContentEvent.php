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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductAssociatedContent;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\ProductAssociatedContent
 */
class ProductAssociatedContentEvent extends ActionEvent
{
    public ?ProductAssociatedContent $content;

    public function __construct(?ProductAssociatedContent $content = null)
    {
        $this->content = $content;
    }

    public function hasProductAssociatedContent(): bool
    {
        return null !== $this->content;
    }

    public function getProductAssociatedContent()
    {
        return $this->content;
    }

    public function setProductAssociatedContent(ProductAssociatedContent $content): static
    {
        $this->content = $content;

        return $this;
    }
}
