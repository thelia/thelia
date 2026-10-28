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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiProvenance;

final class AiProvenanceStoredTest extends TestCase
{
    public function testTheStoredFormIsStableAndReadBackFromArrayAndJson(): void
    {
        $mark = new AiProvenance('theliacms.page_generation', 'claude-opus-5-5', new \DateTimeImmutable('2026-10-28T10:15:00+00:00'), 9001);

        self::assertSame(
            '{"ai_generated":true,"usage":"theliacms.page_generation","model":"claude-opus-5-5","generated_at":"2026-10-28T10:15:00+00:00","log_id":9001}',
            json_encode($mark->toArray(), \JSON_THROW_ON_ERROR),
        );
        self::assertEquals($mark, AiProvenance::fromStored($mark->toArray()));
        self::assertEquals($mark, AiProvenance::fromStored(json_encode($mark->toArray(), \JSON_THROW_ON_ERROR)));
    }

    /**
     * @param array<string, mixed>|string|null $stored
     */
    #[DataProvider('notAMark')]
    public function testAnythingElseIsNoMark(array|string|null $stored): void
    {
        self::assertNull(AiProvenance::fromStored($stored));
    }

    /**
     * @return iterable<string, array{array<string, mixed>|string|null}>
     */
    public static function notAMark(): iterable
    {
        yield 'null' => [null];
        yield 'not json' => ['{oops'];
        yield 'flag false' => [['ai_generated' => false, 'usage' => 'a.b', 'model' => 'm', 'generated_at' => '2026-10-28T10:15:00+00:00', 'log_id' => 1]];
        yield 'no model' => [['ai_generated' => true, 'usage' => 'a.b', 'generated_at' => '2026-10-28T10:15:00+00:00', 'log_id' => 1]];
        yield 'bad date' => [['ai_generated' => true, 'usage' => 'a.b', 'model' => 'm', 'generated_at' => 'yesterday-ish', 'log_id' => 1]];
        yield 'bad usage' => [['ai_generated' => true, 'usage' => 'NOPE', 'model' => 'm', 'generated_at' => '2026-10-28T10:15:00+00:00', 'log_id' => 1]];
        yield 'log id as text' => [['ai_generated' => true, 'usage' => 'a.b', 'model' => 'm', 'generated_at' => '2026-10-28T10:15:00+00:00', 'log_id' => '1']];
    }
}
