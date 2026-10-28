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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Domain\Ai\Tool\AiCreatedToolToken;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenHasher;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenRateLimiter;

/**
 * The format of an MCP token, its fingerprint and the limits of a token.
 */
final class AiToolTokenHasherTest extends TestCase
{
    public function testATokenIsThePrefixAnd43CharactersOfBase62(): void
    {
        $hasher = new AiToolTokenHasher();
        $token = $hasher->generate();

        self::assertSame(1, preg_match('/^thelia_mcp_[0-9A-Za-z]{43}$/D', $token));
        self::assertTrue($hasher->isWellFormed($token));
        self::assertNotSame($token, $hasher->generate());
    }

    public function testTheCharactersOfATokenAreSpreadOverTheWholeAlphabet(): void
    {
        $hasher = new AiToolTokenHasher();
        $seen = [];

        for ($i = 0; $i < 200; ++$i) {
            foreach (str_split(substr($hasher->generate(), \strlen(AiToolTokenHasher::PREFIX))) as $character) {
                $seen[$character] = true;
            }
        }

        self::assertCount(62, $seen);
    }

    public function testOnlyAWellFormedTokenIsRead(): void
    {
        $hasher = new AiToolTokenHasher();
        $good = $hasher->generate();

        foreach (['', 'thelia_mcp_', substr($good, 0, -1), $good.'a', 'other_mcp_'.substr($good, 11), $good."\n", substr($good, 0, -1).'-'] as $bad) {
            self::assertFalse($hasher->isWellFormed($bad), $bad);
        }
    }

    public function testTheFingerprintIsTheSha256AndNotTheToken(): void
    {
        $hasher = new AiToolTokenHasher();
        $token = 'thelia_mcp_'.str_repeat('a', 43);

        self::assertSame(hash('sha256', $token), $hasher->fingerprint($token));
        self::assertSame(64, \strlen($hasher->fingerprint($token)));
        self::assertSame('thelia_mcp_aaaa', $hasher->display($token));
    }

    public function testFingerprintsMatchOnlyWhenEqual(): void
    {
        $hasher = new AiToolTokenHasher();
        $one = $hasher->fingerprint('thelia_mcp_'.str_repeat('a', 43));
        $other = $hasher->fingerprint('thelia_mcp_'.str_repeat('b', 43));

        self::assertTrue($hasher->matches($one, $one));
        self::assertFalse($hasher->matches($one, $other));
        self::assertFalse($hasher->matches($one, ''));
    }

    public function testFingerprintsAreComparedInConstantTime(): void
    {
        $source = (string) file_get_contents((string) (new \ReflectionClass(AiToolTokenHasher::class))->getFileName());

        self::assertStringContainsString('hash_equals(', $source);
        self::assertDoesNotMatchRegularExpression('/\$(fingerprint|storedFingerprint)\s*[!=]==?\s*\$/', $source);
    }

    public function testACreatedTokenKeepsItsClearTextOutOfADump(): void
    {
        $created = new AiCreatedToolToken(3, 'thelia_mcp_'.str_repeat('z', 43), 'thelia_mcp_zzzz', new \DateTimeImmutable('+1 day'));

        ob_start();
        var_dump($created);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(str_repeat('z', 43), $dump);
        self::assertStringContainsString('thelia_mcp_zzzz', $dump);
    }

    public function testATokenIsLimitedToSixtyRequestsAMinute(): void
    {
        $limiter = new AiToolTokenRateLimiter(new ArrayAdapter());

        for ($i = 0; $i < AiToolTokenRateLimiter::PER_MINUTE; ++$i) {
            self::assertNull($limiter->consume(11), 'request '.$i);
        }

        $retryAfter = $limiter->consume(11);

        self::assertInstanceOf(\DateTimeImmutable::class, $retryAfter);
        self::assertGreaterThan(new \DateTimeImmutable(), $retryAfter);
        self::assertNull($limiter->consume(12), 'another token has its own count');
        self::assertSame(2000, AiToolTokenRateLimiter::PER_DAY);
    }
}
