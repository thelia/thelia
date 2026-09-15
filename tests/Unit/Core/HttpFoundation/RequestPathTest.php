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

namespace Thelia\Tests\Unit\Core\HttpFoundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\RequestPath;

/**
 * The router matches a path it has percent-decoded once. Anything in the core that
 * compares a path against a prefix has to look at that same spelling, or a request
 * routed to /api/admin can be told apart from "/api/admin" by writing one letter as %61.
 */
final class RequestPathTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function spellings(): iterable
    {
        yield 'a plain path is returned as is' => ['/api/admin/customers/2', '/api/admin/customers/2'];
        yield 'an encoded letter in a segment' => ['/api/%61dmin/customers/2', '/api/admin/customers/2'];
        yield 'an encoded letter in the middle of a segment' => ['/api/adm%69n/customers/2', '/api/admin/customers/2'];
        yield 'an encoded separator' => ['/api%2Fadmin/customers/2', '/api/admin/customers/2'];
        yield 'an encoded first segment' => ['/%61pi/front/customer_titles', '/api/front/customer_titles'];
        yield 'decoded exactly once, as the router does' => ['/api/%2561dmin', '/api/%61dmin'];
    }

    #[DataProvider('spellings')]
    public function testThePathIsReadTheWayTheRouterReadsIt(string $requested, string $expected): void
    {
        self::assertSame($expected, RequestPath::decoded(Request::create($requested)));
    }
}
