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
use Thelia\Model\SearchSynonym;

class SearchSynonymWriteEvent extends ActionEvent
{
    private ?SearchSynonym $synonym = null;
    private int $langId = 0;
    private string $term = '';

    /** @var list<string> */
    private array $synonyms = [];
    private bool $bidirectional = false;
    private bool $active = true;

    public function getSynonym(): ?SearchSynonym
    {
        return $this->synonym;
    }

    public function setSynonym(?SearchSynonym $synonym): static
    {
        $this->synonym = $synonym;

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

    /**
     * @return list<string>
     */
    public function getSynonyms(): array
    {
        return $this->synonyms;
    }

    /**
     * @param list<string> $synonyms
     */
    public function setSynonyms(array $synonyms): static
    {
        $this->synonyms = $synonyms;

        return $this;
    }

    public function isBidirectional(): bool
    {
        return $this->bidirectional;
    }

    public function setBidirectional(bool $bidirectional): static
    {
        $this->bidirectional = $bidirectional;

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
}
