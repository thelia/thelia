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
    /**
     * @var CategoryAssociatedContent|null
     */
    public $content;

    public function __construct(CategoryAssociatedContent $content = null)
    {
        $this->content = $content;
    }

    public function hasCategoryAssociatedContent(): bool
    {
        return null !== $this->content;
    }

    public function getCategoryAssociatedContent()
    {
        return $this->content;
    }

    public function setCategoryAssociatedContent(CategoryAssociatedContent $content): static
    {
        $this->content = $content;

        return $this;
    }
}
