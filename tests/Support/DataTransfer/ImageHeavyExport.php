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

namespace Thelia\Tests\Support\DataTransfer;

use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;

/**
 * An export of two rows that names many images, and may break on its second row.
 */
final class ImageHeavyExport extends ArrayAbstractExport
{
    public static int $images = 1200;

    public static bool $breaksOnTheSecondRow = false;

    public static string $fileName = 'image-heavy';

    public function getFileName(): string
    {
        return self::$fileName;
    }

    protected function getData(): array
    {
        return [['id' => 1], ['id' => 2]];
    }

    public function hasImages(): bool
    {
        return true;
    }

    public function getImagesPaths(): ?array
    {
        return array_map(static fn (int $i): string => '/nowhere/image-'.$i.'.png', range(1, self::$images));
    }

    public function beforeSerialize(array $data): array
    {
        if (self::$breaksOnTheSecondRow && 2 === ($data['id'] ?? null)) {
            throw new \RuntimeException('The export broke half way.');
        }

        return parent::beforeSerialize($data);
    }
}
