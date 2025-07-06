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
namespace Thelia\Core\Event\Feature;

class FeatureAvCreateEvent extends FeatureAvEvent
{
    protected $title;

    protected $locale;

    protected $feature_id;

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFeatureId()
    {
        return $this->feature_id;
    }

    public function setFeatureId($feature_id): static
    {
        $this->feature_id = $feature_id;

        return $this;
    }
}
