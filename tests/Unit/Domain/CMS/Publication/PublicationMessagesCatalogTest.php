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

namespace Thelia\Tests\Unit\Domain\CMS\Publication;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationDatesException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationTransitionException;
use Thelia\Domain\CMS\Publication\PublicationAction;
use Thelia\Domain\CMS\Publication\PublicationState;

/**
 * Every message a publication exception carries is in the core catalog of the shipped
 * languages, so the API, the command and a back office outside `/admin` translate it.
 */
final class PublicationMessagesCatalogTest extends TestCase
{
    private const array LOCALES = ['en_US', 'fr_FR'];

    #[DataProvider('messages')]
    public function testTheMessageIsInTheCoreCatalog(string $message): void
    {
        foreach (self::LOCALES as $locale) {
            $catalog = require THELIA_LIB.'Config/I18n/'.$locale.'.php';

            self::assertArrayHasKey($message, $catalog, $locale);
            self::assertNotSame('', trim((string) $catalog[$message]), $locale);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function messages(): iterable
    {
        yield 'empty body' => [EmptyContentPublicationException::MESSAGE];
        yield 'withdrawal before publication' => [InvalidPublicationDatesException::withdrawalBeforePublication()->getMessage()];
        yield 'publication date missing' => [InvalidPublicationDatesException::publicationDateMissing()->getMessage()];
        yield 'publication date in the past' => [InvalidPublicationDatesException::publicationDateInThePast()->getMessage()];
        yield 'withdrawal date in the past' => [InvalidPublicationDatesException::withdrawalDateInThePast()->getMessage()];
        yield 'unknown action' => [self::keyOf(InvalidPublicationDatesException::unknownAction('%action')->getMessage())];
        yield 'not a date' => [self::keyOf(InvalidPublicationDatesException::notADate('%date')->getMessage())];
        yield 'transition' => [self::keyOf(InvalidPublicationTransitionException::of(PublicationAction::Cancel, PublicationState::Draft)->getMessage(), ['cancel' => '%action', 'draft' => '%state'])];
    }

    /**
     * Without a translator the message is the key with its parameters substituted: the
     * placeholders are put back to read the key.
     *
     * @param array<string, string> $backToPlaceholders
     */
    private static function keyOf(string $message, array $backToPlaceholders = []): string
    {
        return strtr($message, $backToPlaceholders);
    }
}
