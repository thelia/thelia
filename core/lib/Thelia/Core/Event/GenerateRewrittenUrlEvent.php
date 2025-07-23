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

/**
 * Class GenerateRewrittenUrlEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class GenerateRewrittenUrlEvent extends ActionEvent
{
    protected ?string $url = null;

    public function __construct(
        protected $object,
        protected string $locale,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function setObject($object): static
    {
        $this->object = $object;

        return $this;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isRewritten(): bool
    {
        return null !== $this->url;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
