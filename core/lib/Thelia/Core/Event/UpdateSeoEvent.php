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
namespace Thelia\Core\Event;

class UpdateSeoEvent extends ActionEvent
{
    protected $object;

    public function __construct(protected $object_id, protected $locale = null, protected $url = null, protected $meta_title = null, protected $meta_description = null, protected $meta_keywords = null)
    {
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    /**
     * @return $this
     */
    public function setObjectId($object_id): static
    {
        $this->object_id = $object_id;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return $this
     */
    public function setUrl($url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getMetaTitle()
    {
        return $this->meta_title;
    }

    /**
     * @return $this
     */
    public function setMetaTitle($meta_title): static
    {
        $this->meta_title = $meta_title;

        return $this;
    }

    public function getMetaDescription()
    {
        return $this->meta_description;
    }

    /**
     * @return $this
     */
    public function setMetaDescription($meta_description): static
    {
        $this->meta_description = $meta_description;

        return $this;
    }

    public function getMetaKeywords()
    {
        return $this->meta_keywords;
    }

    /**
     * @return $this
     */
    public function setMetaKeywords($meta_keywords): static
    {
        $this->meta_keywords = $meta_keywords;

        return $this;
    }

    public function setObject($object): static
    {
        $this->object = $object;

        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }
}
