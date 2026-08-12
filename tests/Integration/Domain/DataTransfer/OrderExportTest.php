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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The order export is the only one bounded by dates. `php Thelia export` used
 * to be unable to run it at all, having no way to supply a range.
 */
final class OrderExportTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        // getDataJsonCache() writes its row cache there without creating it;
        // only ExportHandler::processExport() does, and this test does not go
        // through the handler.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testItExportsEveryOrderWhenNoRangeIsGiven(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs(null);

        self::assertContains($old->getRef(), $refs);
        self::assertContains($recent->getRef(), $refs);
    }

    public function testItKeepsOnlyTheOrdersInsideTheRange(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs([
            'start' => new \DateTime('-1 month'),
            'end' => new \DateTime(),
        ]);

        self::assertContains($recent->getRef(), $refs);
        self::assertNotContains($old->getRef(), $refs);
    }

    public function testASingleBoundIsEnoughToFilter(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs(['start' => new \DateTime('-1 month'), 'end' => null]);

        self::assertContains($recent->getRef(), $refs);
        self::assertNotContains($old->getRef(), $refs);
    }

    private function orderCreatedAt(string $modifier): Order
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->setCreatedAt(new \DateTime($modifier));
        $order->save($this->getPropelConnection());

        return $order;
    }

    /**
     * @param array<string, \DateTime|null>|null $rangeDate
     *
     * @return array<int, string>
     */
    private function exportedRefs(?array $rangeDate): array
    {
        $export = new OrderExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate($rangeDate);

        $refs = [];

        foreach ($export as $row) {
            if (isset($row['order_ref'])) {
                $refs[] = $row['order_ref'];
            }
        }

        return $refs;
    }
}
