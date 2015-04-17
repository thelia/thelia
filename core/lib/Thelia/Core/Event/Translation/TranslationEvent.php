<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 * Creation date: 26/03/2015 16:01
 */

namespace Thelia\Core\Event\Translation;

use Thelia\Core\Event\ActionEvent;

class TranslationEvent extends ActionEvent
{
    const WALK_MODE_PHP = 'php';
    const WALK_MODE_TEMPLATE = 'tpl';

    /** @var  string */
    protected $directory;

    /** @var  string */
    protected $mode;

    /** @var  string */
    protected $locale;

    /** @var  string */
    protected $domain;

    /** @var  array */
    protected $translatableStrings;

    /** @var  array */
    protected $customFallbackStrings;

    /** @var  array */
    protected $globalFallbackStrings;

    /** @var  int */
    protected $translatableStringCount;

    /** @var  string */
    protected $translationFilePath;

    /** @var  array */
    protected $translatedStrings;

    /** @var  bool */
    protected $createFileIfNotExists;

    public static function createGetStringsEvent($directory, $mode, $locale, $domain)
    {
        $event = new TranslationEvent();

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
        $createFileIfNotExists
    ) {
        $event = new TranslationEvent();

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
     * @return $this
     */
    public function setDirectory($directory)
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
     * @return $this
     */
    public function setMode($mode)
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
     * @return $this
     */
    public function setLocale($locale)
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
     * @return $this
     */
    public function setDomain($domain)
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
     * @return $this
     */
    public function setTranslatableStrings($translatableStrings)
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
     * @return $this
     */
    public function setTranslatableStringCount($translatableStringCount)
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
     * @return $this
     */
    public function setTranslationFilePath($translationFilePath)
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
     * @return $this
     */
    public function setTranslatedStrings($translatedStrings)
    {
        $this->translatedStrings = $translatedStrings;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isCreateFileIfNotExists()
    {
        return $this->createFileIfNotExists;
    }

    /**
     * @param boolean $createFileIfNotExists
     * @return $this
     */
    public function setCreateFileIfNotExists($createFileIfNotExists)
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
    public function setCustomFallbackStrings($customFallbackStrings)
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
    public function setGlobalFallbackStrings($globalFallbackStrings)
    {
        $this->globalFallbackStrings = $globalFallbackStrings;
        return $this;
    }
}
