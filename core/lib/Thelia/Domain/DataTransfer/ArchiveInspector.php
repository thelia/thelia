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

namespace Thelia\Domain\DataTransfer;

use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;

/**
 * Looks into an uploaded archive before it is extracted.
 *
 * A few kilobytes of archive can hold gigabytes once extracted, or thousands of
 * files, or a name that climbs out of the folder it is extracted to: such an archive
 * is refused before anything is written. An import reads one file of the archive, so
 * the limits are generous for a catalog and tight for an attack.
 */
final readonly class ArchiveInspector
{
    public const MAX_ENTRIES = 1000;

    public const MAX_EXTRACTED_BYTES = 512 * 1024 * 1024;

    public function __construct(
        private int $maxEntries = self::MAX_ENTRIES,
        private int $maxExtractedBytes = self::MAX_EXTRACTED_BYTES,
    ) {
    }

    /**
     * @throws FormValidationException when the archive may not be extracted
     */
    public function assertExtractable(string $path, string $extension): void
    {
        $entries = 'zip' === strtolower($extension) ? self::zipEntries($path) : self::tarEntries($path, strtolower($extension));
        $count = 0;
        $bytes = 0;

        foreach ($entries as $name => $size) {
            if (++$count > $this->maxEntries) {
                throw new FormValidationException(Translator::getInstance()->trans('The archive holds more than %count files.', ['%count' => $this->maxEntries]));
            }

            $bytes += $size;
            if ($bytes > $this->maxExtractedBytes) {
                throw new FormValidationException(Translator::getInstance()->trans('The archive is larger than %size MB once extracted.', ['%size' => intdiv($this->maxExtractedBytes, 1024 * 1024)]));
            }

            if (str_starts_with($name, '/') || \in_array('..', explode('/', str_replace('\\', '/', $name)), true)) {
                throw new FormValidationException(Translator::getInstance()->trans('The archive holds a file outside its own folder.'));
            }
        }
    }

    /**
     * @return \Generator<string, int> the name and the extracted size of each entry
     */
    private static function zipEntries(string $path): \Generator
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new FormValidationException(Translator::getInstance()->trans('The archive cannot be read.'));
        }

        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);

                if (false !== $stat) {
                    yield (string) $stat['name'] => (int) $stat['size'];
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return \Generator<string, int> the name and the extracted size of each entry
     */
    private static function tarEntries(string $path, string $extension): \Generator
    {
        // PharData reads the format from the name, and an upload waits under a name
        // without extension: it is read through a link that has one.
        $named = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('archive-', true).'.'.$extension;

        if (!symlink($path, $named)) {
            throw new FormValidationException(Translator::getInstance()->trans('The archive cannot be read.'));
        }

        try {
            try {
                $archive = new \PharData($named);
            } catch (\UnexpectedValueException) {
                throw new FormValidationException(Translator::getInstance()->trans('The archive cannot be read.'));
            }

            $root = 'phar://'.$archive->getPath().'/';

            /** @var \PharFileInfo $entry */
            foreach (new \RecursiveIteratorIterator($archive) as $entry) {
                yield substr($entry->getPathname(), \strlen($root)) => (int) $entry->getSize();
            }
        } finally {
            unlink($named);
        }
    }
}
