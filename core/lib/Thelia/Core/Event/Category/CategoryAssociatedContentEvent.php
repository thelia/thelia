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

namespace Thelia\Core\Event\Category;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\CategoryAssociatedContent;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\CategoryAssociatedContentEvent
 */
class CategoryAssociatedContentEvent extends ActionEvent
{
    public function __construct(public ?CategoryAssociatedContent $content = null)
    {
    }

    public function hasCategoryAssociatedContent(): bool
    {
        return $this->content instanceof CategoryAssociatedContent;
    }

    public function getCategoryAssociatedContent(): ?CategoryAssociatedContent
    {
        return $this->content;
    }

    public function setCategoryAssociatedContent(CategoryAssociatedContent $content): static
    {
        $this->content = $content;

        return $this;
    }
}
