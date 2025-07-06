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

    /** @var string */
    protected $directory;

    /** @var string */
    protected $mode;

    /** @var string */
    protected $locale;

    /** @var string */
    protected $domain;

    /** @var array */
    protected $translatableStrings;

    /** @var array */
    protected $customFallbackStrings;

    /** @var array */
    protected $globalFallbackStrings;

    /** @var int */
    protected $translatableStringCount;

    /** @var string */
    protected $translationFilePath;

    /** @var array */
    protected $translatedStrings;

    /** @var bool */
    protected $createFileIfNotExists;

    public static function createGetStringsEvent($directory, $mode, $locale, $domain): self
    {
        $event = new self();

        $event->setDirectory($directory);
        $event->setMode($mode);
        $event->setLocale($locale);
        $event->setDomain($domain);

        return $event;
    }

    public static function createWriteFileEvent(
        $translationFilePath,
        $translatableStrings,
        $translatedStrings,
        $createFileIfNotExists,
    ): self {
        $event = new self();

        $event->setTranslatableStrings($translatableStrings);
        $event->setTranslatedStrings($translatedStrings);
        $event->setCreateFileIfNotExists($createFileIfNotExists);
        $event->setTranslationFilePath($translationFilePath);

        return $event;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param string $directory
     *
     * @return $this
     */
    public function setDirectory($directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     *
     * @return $this
     */
    public function setMode($mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     *
     * @return $this
     */
    public function setDomain($domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return array
     */
    public function getTranslatableStrings()
    {
        return $this->translatableStrings;
    }

    /**
     * @param array $translatableStrings
     *
     * @return $this
     */
    public function setTranslatableStrings($translatableStrings): self
    {
        $this->translatableStrings = $translatableStrings;

        return $this;
    }

    /**
     * @return int
     */
    public function getTranslatableStringCount()
    {
        return $this->translatableStringCount;
    }

    /**
     * @param int $translatableStringCount
     *
     * @return $this
     */
    public function setTranslatableStringCount($translatableStringCount): self
    {
        $this->translatableStringCount = $translatableStringCount;

        return $this;
    }

    /**
     * @return string
     */
    public function getTranslationFilePath()
    {
        return $this->translationFilePath;
    }

    /**
     * @param string $translationFilePath
     *
     * @return $this
     */
    public function setTranslationFilePath($translationFilePath): self
    {
        $this->translationFilePath = $translationFilePath;

        return $this;
    }

    /**
     * @return array
     */
    public function getTranslatedStrings()
    {
        return $this->translatedStrings;
    }

    /**
     * @param array $translatedStrings
     *
     * @return $this
     */
    public function setTranslatedStrings($translatedStrings): self
    {
        $this->translatedStrings = $translatedStrings;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCreateFileIfNotExists()
    {
        return $this->createFileIfNotExists;
    }

    /**
     * @param bool $createFileIfNotExists
     *
     * @return $this
     */
    public function setCreateFileIfNotExists($createFileIfNotExists): self
    {
        $this->createFileIfNotExists = $createFileIfNotExists;

        return $this;
    }

    /**
     * @return array
     */
    public function getCustomFallbackStrings()
    {
        return $this->customFallbackStrings;
    }

    /**
     * @param array $customFallbackStrings
     */
    public function setCustomFallbackStrings($customFallbackStrings): static
    {
        $this->customFallbackStrings = $customFallbackStrings;

        return $this;
    }

    /**
     * @return array
     */
    public function getGlobalFallbackStrings()
    {
        return $this->globalFallbackStrings;
    }

    /**
     * @param array $globalFallbackStrings
     */
    public function setGlobalFallbackStrings($globalFallbackStrings): static
    {
        $this->globalFallbackStrings = $globalFallbackStrings;

        return $this;
    }
}
