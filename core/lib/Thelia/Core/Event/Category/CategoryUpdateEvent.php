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

class CategoryUpdateEvent extends CategoryCreateEvent
{
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $parent;
    protected $defaultTemplateId;

    /**
     * @param int $category_id
     */
    public function __construct(protected $category_id)
    {
    }

    public function getCategoryId()
    {
        return $this->category_id;
    }

    public function setCategoryId($category_id): static
    {
        $this->category_id = $category_id;

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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description): static
    {
        $this->description = $description;

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

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent($parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getDefaultTemplateId(): int
    {
        return $this->defaultTemplateId;
    }

    /**
     * @return $this
     */
    public function setDefaultTemplateId(int $defaultTemplateId): static
    {
        $this->defaultTemplateId = $defaultTemplateId;

        return $this;
    }
}
