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

namespace Thelia\Tests\Unit\Domain\Ai\Tool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\AiAudience;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiToolRisk;
use Thelia\Domain\Ai\Tool\AsAiTool;

/**
 * The rules of a declaration that need no class: name, audiences, and above all the right.
 */
final class AsAiToolTest extends TestCase
{
    public function testAnAdministratorToolWithAResourceIsValid(): void
    {
        $tool = new AsAiTool('shop_read', 'Reads.', resource: AdminResources::PRODUCT);

        self::assertSame([AiAudience::Admin], $tool->audiences);
        self::assertSame(AccessManager::VIEW, $tool->access);
        self::assertSame(16384, $tool->maxResultBytes);
        self::assertTrue($tool->mcp);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'an empty resource is skipped by the access check' => [['resource' => ''], 'declares an empty right'];
        yield 'an empty module is skipped by the access check' => [['module' => ''], 'declares an empty right'];
        yield 'an administrator tool without any right is open to every administrator' => [[], 'declares no right'];
        yield 'a resource and a module' => [['resource' => AdminResources::PRODUCT, 'module' => 'Shop'], 'a resource and a module'];
        yield 'the audience system' => [['resource' => AdminResources::PRODUCT, 'audiences' => [AiAudience::Admin, AiAudience::System]], 'audience "system"'];
        yield 'no audience' => [['resource' => AdminResources::PRODUCT, 'audiences' => []], 'at least one audience'];
        yield 'an audience twice' => [['resource' => AdminResources::PRODUCT, 'audiences' => [AiAudience::Admin, AiAudience::Admin]], 'each once'];
        yield 'administrators mixed with visitors' => [['audiences' => [AiAudience::Admin, AiAudience::Public], 'resource' => AdminResources::PRODUCT], 'mixes the audience'];
        yield 'a visitor tool with a right' => [['audiences' => [AiAudience::Public], 'resource' => AdminResources::PRODUCT], 'declares no right'];
        yield 'a visitor tool with private data' => [['audiences' => [AiAudience::Public], 'risks' => [AiToolRisk::PrivateData]], 'returns private data'];
        yield 'an unknown access' => [['resource' => AdminResources::PRODUCT, 'access' => 'ALL'], 'VIEW, CREATE, UPDATE or DELETE'];
        yield 'a result above the ceiling' => [['resource' => AdminResources::PRODUCT, 'maxResultBytes' => 262145], 'limited to 1..262144'];
        yield 'a risk twice' => [['resource' => AdminResources::PRODUCT, 'risks' => [AiToolRisk::PrivateData, AiToolRisk::PrivateData]], 'repeats a risk'];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalidDeclarations')]
    public function testADeclarationThatBreaksARuleIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage($message);

        new AsAiTool(...['name' => 'shop_read', 'description' => 'Reads.', ...$arguments]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'no prefix' => ['search'];
        yield 'upper case' => ['Shop_search'];
        yield 'a dash' => ['shop_search-products'];
        yield 'too long' => ['shop_abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'];
        yield 'a prefix of 32 characters' => [str_repeat('a', 32).'_x'];
        yield 'an empty action' => ['shop_'];
        yield 'a space' => ['shop_a b'];
    }

    #[DataProvider('invalidNames')]
    public function testANameOutsideThePatternIsRefused(string $name): void
    {
        $this->expectException(InvalidAiArgumentException::class);

        new AsAiTool($name, 'Reads.', resource: AdminResources::PRODUCT);
    }

    public function testTheLongestNameTheProvidersAcceptIsValid(): void
    {
        $name = str_repeat('a', 23).'_'.str_repeat('b', 40);

        self::assertSame(64, \strlen($name));
        self::assertSame($name, (new AsAiTool($name, 'Reads.', resource: AdminResources::PRODUCT))->name);
    }

    public function testAVisitorToolWithoutRightIsValid(): void
    {
        $tool = new AsAiTool('shop_policies', 'Reads the policies.', audiences: [AiAudience::Public, AiAudience::Customer], risks: [AiToolRisk::UntrustedContent]);

        self::assertFalse($tool->isForAdministrators());
    }
}
