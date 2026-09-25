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

namespace Thelia\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Security\Token\TokenProvider;

final class TokenProviderTest extends TestCase
{
    public function testAWellFormedKeyIsReadBack(): void
    {
        self::assertSame(
            ['username' => 'jane', 'token' => 'a-token', 'serial' => 'a-serial'],
            (new TokenProvider())->decodeKey(base64_encode("jane\0a-token\0a-serial")),
        );
    }

    public static function malformedKeys(): iterable
    {
        yield 'not base64' => ['not-base64!'];
        yield 'missing parts' => [base64_encode('jane')];
        yield 'empty' => [''];
    }

    #[DataProvider('malformedKeys')]
    public function testAMalformedKeyReadsAsNoOneInsteadOfFailing(string $key): void
    {
        self::assertSame(['username' => '', 'token' => '', 'serial' => ''], (new TokenProvider())->decodeKey($key));
    }
}
