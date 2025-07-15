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

class FeatureUpdateEvent extends FeatureCreateEvent
{
    protected int $feature_id;

    protected $description;

    protected $chapo;

    protected $postscriptum;

    public function __construct(int $feature_id)
    {
        $this->setFeatureId($feature_id);
    }

    public function getFeatureId(): int
    {
        return $this->feature_id;
    }

    public function setFeatureId(int $feature_id): static
    {
        $this->feature_id = $feature_id;

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

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo): static
    {
        $this->chapo = $chapo;

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
}
