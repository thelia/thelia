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

namespace Thelia\Tests\Unit\Domain\Media;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Media\DTO\ImageUpdateDTO;

/**
 * What an image edit keeps of the merchant's input: a field the merchant left out stays
 * null, and one they set to false is kept as false.
 */
final class ImageUpdateDTOTest extends TestCase
{
    public function testAnEditThatTouchesOnlyTheLocaleLeavesEveryOtherFieldUntouched(): void
    {
        self::assertSame([
            'locale' => 'fr_FR',
            'title' => null,
            'chapo' => null,
            'description' => null,
            'postscriptum' => null,
            'visible' => null,
            'alt' => null,
            'decorative' => null,
        ], (new ImageUpdateDTO(locale: 'fr_FR'))->toArray());
    }

    public function testTheEventPayloadCarriesTheAccessibilityFields(): void
    {
        $dto = new ImageUpdateDTO(
            locale: 'en_US',
            title: 'Front',
            chapo: 'Chapo',
            description: 'Description',
            postscriptum: 'Postscriptum',
            visible: false,
            alt: 'A leather bag seen from the front',
            decorative: false,
        );

        self::assertSame([
            'locale' => 'en_US',
            'title' => 'Front',
            'chapo' => 'Chapo',
            'description' => 'Description',
            'postscriptum' => 'Postscriptum',
            'visible' => false,
            'alt' => 'A leather bag seen from the front',
            'decorative' => false,
        ], $dto->toArray());
    }

    public function testAnImageFlaggedDecorativeIsReportedAsSuch(): void
    {
        self::assertTrue((new ImageUpdateDTO(locale: 'en_US', decorative: true))->toArray()['decorative']);
    }
}
