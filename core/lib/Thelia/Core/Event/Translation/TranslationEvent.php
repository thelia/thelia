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

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 * Creation date: 26/03/2015 16:01
 */

namespace Thelia\Core\Event\Translation;

use Thelia\Core\Event\ActionEvent;

class TranslationEvent extends ActionEvent
{
    public const WALK_MODE_PHP = 'php';
    public const WALK_MODE_TEMPLATE = 'tpl';

    protected string $directory;
    protected string $mode;
    protected string $locale;
    protected string $domain;
    protected array $translatableStrings;
    protected array $customFallbackStrings;
    protected array $globalFallbackStrings;
    protected int $translatableStringCount;
    protected string $translationFilePath;
    protected array $translatedStrings;
    protected bool $createFileIfNotExists;

    public static function createGetStringsEvent(string $directory, string $mode, string $locale, string $domain): self
    {
        $event = new self();

        $event->setDirectory($directory);
        $event->setMode($mode);
        $event->setLocale($locale);
        $event->setDomain($domain);

        return $event;
    }

    public static function createWriteFileEvent(
        string $translationFilePath,
        array $translatableStrings,
        array $translatedStrings,
        bool $createFileIfNotExists,
    ): self {
        $event = new self();

        $event->setTranslatableStrings($translatableStrings);
        $event->setTranslatedStrings($translatedStrings);
        $event->setCreateFileIfNotExists($createFileIfNotExists);
        $event->setTranslationFilePath($translationFilePath);

        return $event;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * @return $this
     */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * @return $this
     */
    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return $this
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getTranslatableStrings(): array
    {
        return $this->translatableStrings;
    }

    /**
     * @return $this
     */
    public function setTranslatableStrings(array $translatableStrings): self
    {
        $this->translatableStrings = $translatableStrings;

        return $this;
    }

    public function getTranslatableStringCount(): int
    {
        return $this->translatableStringCount;
    }

    /**
     * @return $this
     */
    public function setTranslatableStringCount(int $translatableStringCount): self
    {
        $this->translatableStringCount = $translatableStringCount;

        return $this;
    }

    public function getTranslationFilePath(): string
    {
        return $this->translationFilePath;
    }

    /**
     * @return $this
     */
    public function setTranslationFilePath(string $translationFilePath): self
    {
        $this->translationFilePath = $translationFilePath;

        return $this;
    }

    public function getTranslatedStrings(): array
    {
        return $this->translatedStrings;
    }

    /**
     * @return $this
     */
    public function setTranslatedStrings(array $translatedStrings): self
    {
        $this->translatedStrings = $translatedStrings;

        return $this;
    }

    public function isCreateFileIfNotExists(): bool
    {
        return $this->createFileIfNotExists;
    }

    /**
     * @return $this
     */
    public function setCreateFileIfNotExists(bool $createFileIfNotExists): self
    {
        $this->createFileIfNotExists = $createFileIfNotExists;

        return $this;
    }

    public function getCustomFallbackStrings(): array
    {
        return $this->customFallbackStrings;
    }

    public function setCustomFallbackStrings(array $customFallbackStrings): static
    {
        $this->customFallbackStrings = $customFallbackStrings;

        return $this;
    }

    public function getGlobalFallbackStrings(): array
    {
        return $this->globalFallbackStrings;
    }

    public function setGlobalFallbackStrings(array $globalFallbackStrings): static
    {
        $this->globalFallbackStrings = $globalFallbackStrings;

        return $this;
    }
}
