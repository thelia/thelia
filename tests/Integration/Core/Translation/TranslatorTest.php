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

namespace Thelia\Tests\Integration\Core\Translation;

use Thelia\Core\Translation\Translator;
use Thelia\Test\IntegrationTestCase;

final class TranslatorTest extends IntegrationTestCase
{
    public function testGetInstanceReturnsSingletonAfterBoot(): void
    {
        $translator = Translator::getInstance();

        self::assertInstanceOf(Translator::class, $translator);
    }

    public function testTransReturnsKeyWhenNoCatalogExists(): void
    {
        $translator = Translator::getInstance();

        // A key that is guaranteed to not exist in any catalog.
        $key = 'test.missing.translation.key.'.uniqid();
        $result = $translator->trans($key);

        // When no translation is found, the key itself is returned.
        self::assertSame($key, $result);
    }

    public function testTransWithParametersSubstitutesPlaceholders(): void
    {
        $translator = Translator::getInstance();

        // Even without a catalog match, parameter substitution should work.
        $key = 'Hello %name%';
        $result = $translator->trans($key, ['%name%' => 'World']);

        self::assertSame('Hello World', $result);
    }

    public function testTransWithDomainFallsBackToKey(): void
    {
        $translator = Translator::getInstance();

        $key = 'nonexistent.in.domain.'.uniqid();
        $result = $translator->trans($key, [], 'backOffice.default');

        self::assertSame($key, $result);
    }
}
