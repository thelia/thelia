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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Domain\Order\Exception\PickingSlipException;
use Thelia\Domain\Order\Service\PickingSlipPdfService;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Order\PickingSlipFixtureTheme;

final class PickingSlipPdfServiceTest extends IntegrationTestCase
{
    use PickingSlipFixtureTheme;

    protected function tearDown(): void
    {
        $this->removePickingSlipTheme();
        ConfigQuery::write(PickingSlipPdfService::CONFIG_MAX_ORDERS, '');

        parent::tearDown();
    }

    public function testOneOrderGivesOnePageNamedAfterItsReference(): void
    {
        $this->installPickingSlipTheme();
        $order = $this->createFixtureFactory()->order();

        $slip = $this->service()->render([$order->getId()]);

        self::assertStringStartsWith('%PDF', $slip->content);
        self::assertSame(1, $this->pageCount($slip->content));
        self::assertSame('preparation-'.$order->getRef().'.pdf', $slip->fileName);
        self::assertSame(1, $slip->orderCount);
    }

    public function testABatchGivesOnePagePerOrderNamedAfterTheDate(): void
    {
        $this->installPickingSlipTheme();
        $factory = $this->createFixtureFactory();
        $first = $factory->order();
        $second = $factory->order();

        $slip = $this->service()->render([$second->getId(), $first->getId()]);

        self::assertSame(2, $this->pageCount($slip->content));
        self::assertSame('preparation-'.date('Y-m-d').'.pdf', $slip->fileName);
        self::assertSame(2, $slip->orderCount);
    }

    public function testUnknownIdsAreDroppedAndTheTemplateReceivesTheIdsInTheOrderAsked(): void
    {
        $this->installPickingSlipTheme();
        $factory = $this->createFixtureFactory();
        $first = $factory->order();
        $second = $factory->order();

        $slip = $this->service()->render([$second->getId(), 0, 99999999, $first->getId(), $second->getId()]);

        self::assertSame(2, $slip->orderCount);
    }

    public function testAnEmptyListIsRefused(): void
    {
        $this->installPickingSlipTheme();

        $this->expectException(PickingSlipException::class);
        $this->expectExceptionMessage('No order to print');

        $this->service()->render([]);
    }

    public function testAListOfUnknownOrdersIsRefused(): void
    {
        $this->installPickingSlipTheme();

        $this->expectException(PickingSlipException::class);

        $this->service()->render([99999998, 99999999]);
    }

    public function testABatchOverTheLimitIsRefusedWithItsLimit(): void
    {
        $this->installPickingSlipTheme();

        $this->expectException(PickingSlipException::class);
        $this->expectExceptionMessage('200 orders at most, 201 were requested');

        $this->service()->render(range(1, 201));
    }

    public function testTheLimitComesFromTheConfiguration(): void
    {
        $this->installPickingSlipTheme();
        ConfigQuery::write(PickingSlipPdfService::CONFIG_MAX_ORDERS, '2');

        $this->expectException(PickingSlipException::class);
        $this->expectExceptionMessage('2 orders at most, 3 were requested');

        $this->service()->render([1, 2, 3]);
    }

    public function testAThemeWithoutThePreparationDocumentIsNamedInTheMessage(): void
    {
        $this->installPickingSlipTheme(withPreparationTemplate: false);
        $order = $this->createFixtureFactory()->order();

        $this->expectException(PickingSlipException::class);
        $this->expectExceptionMessage('The active PDF theme has no preparation document.');

        $this->service()->render([$order->getId()]);
    }

    public function testTheTemplateReceivesTheLocaleOfTheShopDefaultLanguage(): void
    {
        $this->installPickingSlipTheme();
        $order = $this->createFixtureFactory()->order();

        $html = null;
        $this->getService('event_dispatcher')->addListener(
            \Thelia\Core\Event\TheliaEvents::GENERATE_PDF,
            static function (\Thelia\Core\Event\PdfEvent $event) use (&$html): void {
                $html = $event->getContent();
            },
            1000,
        );

        $this->service()->render([$order->getId()]);

        $defaultLocale = \Thelia\Model\Lang::getDefaultLanguage()->getLocale();
        self::assertStringContainsString('SLIP-'.$order->getId().' locale='.$defaultLocale, (string) $html);
        self::assertStringContainsString('data:image/svg+xml;base64,', (string) $html);
    }

    private function service(): PickingSlipPdfService
    {
        return $this->getService(PickingSlipPdfService::class);
    }
}
