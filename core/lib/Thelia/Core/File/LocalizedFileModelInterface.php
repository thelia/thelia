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

namespace Thelia\Core\File;

/**
 * A file model whose file is translated: each language may carry its own file.
 *
 * getFile() answers the file shown in the current locale, falling back on the default
 * language the way translated texts do. The methods below tell the stored files apart,
 * which is what writing or removing one of them needs: after an upgrade every language
 * of an image shares the same file on disk.
 */
interface LocalizedFileModelInterface extends FileModelInterface
{
    /**
     * The file stored for the current locale, null when this language has none of its own.
     */
    public function getOwnFile(): ?string;

    /**
     * Every distinct file stored by the translations of this model.
     *
     * @return list<string>
     */
    public function getStoredFiles(): array;

    /**
     * Whether a translation other than the current locale stores this file.
     */
    public function isFileUsedByAnotherLocale(string $file): bool;
}
