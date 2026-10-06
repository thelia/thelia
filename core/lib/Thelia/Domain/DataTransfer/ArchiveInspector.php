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

use Symfony\Component\Finder\Finder;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\UploadRefusedException;

/**
 * Looks into an uploaded archive before it is extracted, and measures what it wrote
 * once it is.
 *
 * A few kilobytes of archive can hold gigabytes once extracted, or thousands of
 * files, or a name, or a link, that leads out of the folder it is extracted to: such
 * an archive is refused before anything is written. The sizes an archive declares
 * are written by whoever made it, so the extracted folder is measured too. A tar is
 * listed by reading its headers through its compression, one block at a time: the
 * time that takes grows with the archive, the memory and the disk do not. An import
 * reads one file of the archive, so the limits are generous for a catalog and tight
 * for an attack.
 */
final readonly class ArchiveInspector
{
    public const MAX_ENTRIES = 1000;

    public const MAX_EXTRACTED_BYTES = 512 * 1024 * 1024;

    private const UNIX_FILE_TYPE = 0o170000;

    private const UNIX_SYMBOLIC_LINK = 0o120000;

    public function __construct(
        private int $maxEntries = self::MAX_ENTRIES,
        private int $maxExtractedBytes = self::MAX_EXTRACTED_BYTES,
    ) {
    }

    /**
     * @throws UploadRefusedException when the archive may not be extracted
     */
    public function assertExtractable(string $path, string $extension): void
    {
        $extension = strtolower($extension);
        $entries = 'zip' === $extension ? self::zipEntries($path) : self::tarEntries($path, $extension);
        $count = 0;
        $bytes = 0;

        foreach ($entries as [$name, $size, $isLink]) {
            if (++$count > $this->maxEntries) {
                throw new UploadRefusedException(Translator::getInstance()->trans('The archive holds more than %count files.', ['%count' => $this->maxEntries]));
            }

            $bytes += $size;
            if ($bytes > $this->maxExtractedBytes) {
                throw self::tooLarge($this->maxExtractedBytes);
            }

            if ($isLink || str_starts_with($name, '/') || \in_array('..', explode('/', str_replace('\\', '/', $name)), true)) {
                throw new UploadRefusedException(Translator::getInstance()->trans('The archive holds a file outside its own folder.'));
            }
        }
    }

    /**
     * @throws UploadRefusedException when the extracted files weigh more than allowed
     */
    public function assertExtractedSize(string $directory): void
    {
        $bytes = 0;

        foreach ((new Finder())->files()->in($directory)->ignoreDotFiles(false) as $file) {
            $bytes += (int) $file->getSize();

            if ($bytes > $this->maxExtractedBytes) {
                throw self::tooLarge($this->maxExtractedBytes);
            }
        }
    }

    private static function tooLarge(int $maxBytes): UploadRefusedException
    {
        return new UploadRefusedException(Translator::getInstance()->trans('The archive is larger than %size MB once extracted.', ['%size' => intdiv($maxBytes, 1024 * 1024)]));
    }

    /**
     * @return \Generator<int, array{string, int, bool}> the name, the extracted size and
     *                                                   whether each entry is a link
     */
    private static function zipEntries(string $path): \Generator
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw self::unreadable();
        }

        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);

                if (false === $stat) {
                    continue;
                }

                $system = $attributes = 0;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                $isLink = \ZipArchive::OPSYS_UNIX === $system && self::UNIX_SYMBOLIC_LINK === (($attributes >> 16) & self::UNIX_FILE_TYPE);

                yield [(string) $stat['name'], (int) $stat['size'], $isLink];
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Reads the headers of a tar, through its compression, one block at a time: the
     * archive is never held in memory, as PharData would hold it.
     *
     * @return \Generator<int, array{string, int, bool}> the name, the extracted size and
     *                                                   whether each entry is a link
     */
    private static function tarEntries(string $path, string $extension): \Generator
    {
        $wrapper = match ($extension) {
            'tgz', 'gz' => 'compress.zlib://',
            'bz2', 'tbz2' => 'compress.bzip2://',
            default => '',
        };
        $stream = @fopen($wrapper.$path, 'r');

        if (false === $stream) {
            throw self::unreadable();
        }

        try {
            $longName = null;

            while (true) {
                $header = self::readBlock($stream, 512);

                if (null === $header || '' === trim($header, "\0")) {
                    return;
                }

                $name = rtrim(substr($header, 0, 100), "\0");
                $prefix = rtrim(substr($header, 345, 155), "\0");
                $size = (int) octdec(trim(substr($header, 124, 12), "\0 "));
                $type = $header[156];

                // GNU long names and pax records name the entry that follows.
                if ('L' === $type) {
                    $longName = rtrim((string) self::skipOrRead($stream, $size, true), "\0");

                    continue;
                }

                if ('x' === $type || 'g' === $type) {
                    if (1 === preg_match('/\d+ path=([^\n]*)\n/', (string) self::skipOrRead($stream, $size, true), $match)) {
                        $longName = $match[1];
                    }

                    continue;
                }

                $fullName = $longName ?? ('' === $prefix ? $name : $prefix.'/'.$name);
                $longName = null;

                // Told before its content is read: an entry over the limits stops the
                // reading there. Directories hold nothing; links point elsewhere.
                if ('5' !== $type) {
                    yield [$fullName, $size, '1' === $type || '2' === $type];
                }

                self::skipOrRead($stream, $size, false);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     */
    private static function readBlock($stream, int $length): ?string
    {
        $block = '';

        while (\strlen($block) < $length && !feof($stream)) {
            $chunk = fread($stream, $length - \strlen($block));

            if (false === $chunk || '' === $chunk) {
                break;
            }

            $block .= $chunk;
        }

        return \strlen($block) === $length ? $block : null;
    }

    /**
     * Moves past the content of an entry, padded to 512 bytes, and gives it back when
     * it is a header record (a long name, pax attributes).
     *
     * @param resource $stream
     */
    private static function skipOrRead($stream, int $size, bool $keep): ?string
    {
        $padded = (int) (ceil($size / 512) * 512);
        $kept = '';

        for ($left = $padded; $left > 0; $left -= \strlen($chunk)) {
            $chunk = fread($stream, min($left, 1024 * 1024));

            if (false === $chunk || '' === $chunk) {
                throw self::unreadable();
            }

            if ($keep && \strlen($kept) < 65536) {
                $kept .= $chunk;
            }
        }

        return $keep ? substr($kept, 0, $size) : null;
    }

    private static function unreadable(): UploadRefusedException
    {
        return new UploadRefusedException(Translator::getInstance()->trans('The archive cannot be read.'));
    }
}
