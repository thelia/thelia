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

namespace Thelia\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Module\ModuleDescriptor;

/**
 * The activation a descriptor declares decides the state a module lands in on a fresh
 * install: a missing element means active as before, and a value that is neither 0 nor 1
 * must stop the install rather than pick a state.
 */
final class ModuleDescriptorTest extends TestCase
{
    public function testADescriptorWithoutTheElementIsEnabled(): void
    {
        self::assertTrue(ModuleDescriptor::enabledByDefault($this->descriptor(''), 'module.xml'));
    }

    #[DataProvider('declaredValues')]
    public function testTheDeclaredValueIsHonoured(string $value, bool $expected): void
    {
        self::assertSame($expected, ModuleDescriptor::enabledByDefault($this->descriptor("<enabled-by-default>{$value}</enabled-by-default>"), 'module.xml'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function declaredValues(): iterable
    {
        yield 'one' => ['1', true];
        yield 'zero' => ['0', false];
        yield 'zero with surrounding whitespace' => [' 0 ', false];
    }

    public function testAnotherValueIsRefusedAndNamesTheDescriptor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('<enabled-by-default> in vendor/acme/modules/Acme/Config/module.xml must be 0 or 1, "maybe" given.');

        ModuleDescriptor::enabledByDefault($this->descriptor('<enabled-by-default>maybe</enabled-by-default>'), 'vendor/acme/modules/Acme/Config/module.xml');
    }

    private function descriptor(string $trailingElements): \SimpleXMLElement
    {
        $xml = simplexml_load_string(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module">
                <fullnamespace>Acme\\Acme</fullnamespace>
                <version>1.0.0</version>
                <mandatory>0</mandatory>
                <hidden>0</hidden>
                {$trailingElements}
            </module>
            XML);

        self::assertNotFalse($xml);

        return $xml;
    }
}
