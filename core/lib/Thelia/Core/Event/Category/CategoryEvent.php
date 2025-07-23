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
use Thelia\Model\Category;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\CategoryEvent
 */
class CategoryEvent extends ActionEvent
{
    public function __construct(public ?Category $category = null)
    {
    }

    public function hasCategory(): bool
    {
        return $this->category instanceof Category;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
