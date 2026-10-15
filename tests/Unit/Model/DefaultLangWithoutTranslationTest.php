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

namespace Thelia\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 * The `default_lang_without_translation` variable is stored as a string ('0' or '1'), while
 * the Lang constants it is compared to are integers: the mode must be the same integer
 * whether the variable has a row in the config table or falls back to its default.
 */
final class DefaultLangWithoutTranslationTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
    }

    /**
     * @return iterable<string, array{array<string, string>, int}>
     */
    public static function storedModes(): iterable
    {
        yield 'stored replace' => [['default_lang_without_translation' => '1'], Lang::REPLACE_BY_DEFAULT_LANGUAGE];
        yield 'stored strict' => [['default_lang_without_translation' => '0'], Lang::STRICTLY_USE_REQUESTED_LANGUAGE];
        yield 'no row' => [[], Lang::REPLACE_BY_DEFAULT_LANGUAGE];
    }

    /**
     * @param array<string, string> $configs
     */
    #[DataProvider('storedModes')]
    public function testTheModeIsReadAsAnInteger(array $configs, int $expected): void
    {
        ConfigQuery::initCache($configs);

        self::assertSame($expected, ConfigQuery::getDefaultLangWhenNoTranslationAvailable());
    }
}
