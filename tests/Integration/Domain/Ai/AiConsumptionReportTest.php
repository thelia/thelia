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

namespace Thelia\Tests\Integration\Domain\Ai;

use Thelia\Domain\Ai\Log\AiConsumptionReport;
use Thelia\Model\AiUsageLog;
use Thelia\Test\IntegrationTestCase;

final class AiConsumptionReportTest extends IntegrationTestCase
{
    public function testTheLogIsSummedByEachDimensionOverTheChosenDaysOnly(): void
    {
        $this->row('rep.chat', 'rep', 'model-a', 'completion', '0.30000000', '2031-03-01 23:59:59');
        $this->row('rep.chat', 'rep', 'model-a', 'stream', '0.20000000', '2031-03-02 08:00:00');
        $this->row('rep.pages', 'rep', 'model-b', 'completion', '0.10000000', '2031-03-02 09:00:00');
        $this->row('rep.pages', 'rep', null, 'external', '0.05000000', '2031-03-02 10:00:00');
        $this->row('rep.chat', 'rep', 'model-a', 'completion', '9.00000000', '2031-03-03 00:00:00');
        $this->row('rep.chat', 'rep', 'model-a', 'completion', '9.00000000', '2031-02-28 23:59:59');

        $report = new AiConsumptionReport();
        $from = new \DateTimeImmutable('2031-03-01');
        $to = new \DateTimeImmutable('2031-03-02');

        self::assertSame([['label' => 'rep.chat', 'calls' => 2, 'cost' => '0.50000000'], ['label' => 'rep.pages', 'calls' => 1, 'cost' => '0.15000000']], $report->by(AiConsumptionReport::BY_USAGE, $from, $to));
        self::assertSame([['label' => 'rep', 'calls' => 3, 'cost' => '0.65000000']], $report->by(AiConsumptionReport::BY_MODULE, $from, $to));
        self::assertSame([['label' => 'model-a', 'calls' => 2, 'cost' => '0.50000000'], ['label' => 'model-b', 'calls' => 1, 'cost' => '0.10000000'], ['label' => '-', 'calls' => 0, 'cost' => '0.05000000']], $report->by(AiConsumptionReport::BY_MODEL, $from, $to));
        self::assertSame([['label' => '2031-03-01', 'calls' => 1, 'cost' => '0.30000000'], ['label' => '2031-03-02', 'calls' => 2, 'cost' => '0.35000000']], $report->by(AiConsumptionReport::BY_DAY, $from, $to));
    }

    public function testAnUnknownDimensionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AiConsumptionReport())->by('`; DROP', new \DateTimeImmutable('2031-03-01'), new \DateTimeImmutable('2031-03-02'));
    }

    private function row(string $usage, string $module, ?string $model, string $kind, string $cost, string $at): void
    {
        $row = (new AiUsageLog())
            ->setUsageId($usage)->setModule($module)->setModel($model)->setKind($kind)->setCost($cost)
            ->setActorType('system')->setActorHash(str_repeat('a', 64))->setStatus('succeeded')
            ->setCreatedAt(new \DateTimeImmutable($at));
        $row->save();
    }
}
