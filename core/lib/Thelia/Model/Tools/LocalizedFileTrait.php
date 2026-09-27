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

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 * The file of an image model, stored per language in its translations.
 *
 * Reading the file of a language without one of its own follows the shop setting that
 * governs every translated text (`default_lang_without_translation`): the file of the
 * default language when the shop replaces missing translations, nothing otherwise.
 *
 * The translations read here are never added to the model: a read must not leave behind
 * an empty translation that the next save() would write.
 *
 * @see \Thelia\Core\File\LocalizedFileModelInterface
 */
trait LocalizedFileTrait
{
    public function getFile(): string
    {
        $file = $this->getOwnFile();

        if (null !== $file) {
            return $file;
        }

        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return '';
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        if ($defaultLocale === $this->getLocale()) {
            return '';
        }

        return $this->storedFileOf($defaultLocale) ?? '';
    }

    public function getOwnFile(): ?string
    {
        return $this->storedFileOf($this->getLocale());
    }

    public function getStoredFiles(): array
    {
        $files = $this->storedFilesByLocale();

        return array_values(array_unique(array_values($files)));
    }

    public function isFileUsedByAnotherLocale(string $file): bool
    {
        foreach ($this->storedFilesByLocale() as $locale => $storedFile) {
            if ($locale !== $this->getLocale() && $storedFile === $file) {
                return true;
            }
        }

        return false;
    }

    private function storedFileOf(string $locale): ?string
    {
        if (isset($this->currentTranslations[$locale])) {
            return self::nonEmptyFile($this->currentTranslations[$locale]->getFile());
        }

        if ($this->isNew()) {
            return null;
        }

        $translation = $this->createTranslationQuery()->findPk([$this->getId(), $locale]);

        return null === $translation ? null : self::nonEmptyFile($translation->getFile());
    }

    /**
     * The stored file of each language, pending changes on this model included.
     *
     * @return array<string, string>
     */
    private function storedFilesByLocale(): array
    {
        $files = [];

        if (!$this->isNew()) {
            foreach ($this->createTranslationQuery()->filterById($this->getId())->find() as $translation) {
                $file = self::nonEmptyFile($translation->getFile());

                if (null !== $file) {
                    $files[$translation->getLocale()] = $file;
                }
            }
        }

        foreach ($this->currentTranslations ?? [] as $locale => $translation) {
            $file = self::nonEmptyFile($translation->getFile());

            if (null === $file) {
                unset($files[$locale]);
            } else {
                $files[$locale] = $file;
            }
        }

        return $files;
    }

    private function createTranslationQuery(): ModelCriteria
    {
        $queryClass = self::class.'I18nQuery';

        return $queryClass::create();
    }

    private static function nonEmptyFile(?string $file): ?string
    {
        return null === $file || '' === $file ? null : $file;
    }
}
