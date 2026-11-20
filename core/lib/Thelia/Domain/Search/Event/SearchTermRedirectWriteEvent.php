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

namespace Thelia\Domain\Search\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\SearchTermRedirect;

class SearchTermRedirectWriteEvent extends ActionEvent
{
    private ?SearchTermRedirect $redirect = null;
    private int $langId = 0;
    private string $term = '';
    private string $targetType = '';
    private ?int $targetId = null;
    private ?string $url = null;
    private bool $active = true;
    private bool $commonTerm = false;
    private bool $commonTermChecked = true;

    public function getRedirect(): ?SearchTermRedirect
    {
        return $this->redirect;
    }

    public function setRedirect(?SearchTermRedirect $redirect): static
    {
        $this->redirect = $redirect;

        return $this;
    }

    public function getLangId(): int
    {
        return $this->langId;
    }

    public function setLangId(int $langId): static
    {
        $this->langId = $langId;

        return $this;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): static
    {
        $this->term = $term;

        return $this;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function setTargetId(?int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isCommonTerm(): bool
    {
        return $this->commonTerm;
    }

    public function setCommonTerm(bool $commonTerm): static
    {
        $this->commonTerm = $commonTerm;

        return $this;
    }

    public function isCommonTermChecked(): bool
    {
        return $this->commonTermChecked;
    }

    public function setCommonTermChecked(bool $commonTermChecked): static
    {
        $this->commonTermChecked = $commonTermChecked;

        return $this;
    }
}
