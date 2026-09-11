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

namespace Thelia\Tests\Integration\Model;

use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\Sale;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A sale operation is a page of the shop — the one a countdown and a private drop
 * are shown on — so it needs an address of its own, generated from its title the
 * way a product's is.
 */
final class SaleUrlRewritingTest extends ActionIntegrationTestCase
{
    public function testASaleOperationAnswersTheSaleView(): void
    {
        self::assertSame('sale', (new Sale())->getRewrittenUrlViewName());
    }

    public function testCreatingAnOperationGeneratesItsAddress(): void
    {
        $sale = $this->dispatch(
            (new SaleCreateEvent())
                ->setLocale('en_US')
                ->setTitle('Private winter drop')
                ->setSaleLabel('DROP'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        $rewritingUrl = RewritingUrlQuery::create()
            ->filterByView('sale')
            ->filterByViewId((string) $sale->getId())
            ->filterByViewLocale('en_US')
            ->findOne();

        self::assertNotNull($rewritingUrl, 'The operation has to be reachable at an address of its own.');
        self::assertSame('private-winter-drop.html', $rewritingUrl->getUrl());
    }

    public function testTheOperationHandsOutThatAddress(): void
    {
        $sale = $this->dispatch(
            (new SaleCreateEvent())
                ->setLocale('en_US')
                ->setTitle('Members only sale')
                ->setSaleLabel('MEMBERS'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        self::assertStringEndsWith('members-only-sale.html', $sale->getUrl('en_US'));
    }

    /**
     * A second operation with the same title gets an address of its own rather than
     * stealing the first one's.
     */
    public function testTwoOperationsWithTheSameTitleGetTwoAddresses(): void
    {
        $titles = ['Flash sale', 'Flash sale'];
        $urls = [];

        foreach ($titles as $title) {
            $sale = $this->dispatch(
                (new SaleCreateEvent())->setLocale('en_US')->setTitle($title)->setSaleLabel('FLASH'),
                TheliaEvents::SALE_CREATE,
            )->getSale();

            $urls[] = $sale->getUrl('en_US');
        }

        self::assertNotSame($urls[0], $urls[1]);
    }
}
