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

namespace Thelia\Tests\Integration\Domain\Order\Reminder;

use Thelia\Domain\Order\Reminder\UnpaidOrderPaymentLink;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class UnpaidOrderPaymentLinkTest extends ActionIntegrationTestCase
{
    private UnpaidOrderPaymentLink $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->link = $this->getService(UnpaidOrderPaymentLink::class);
    }

    public function testATokenOpensTheOrderItWasIssuedFor(): void
    {
        $order = $this->unpaidOrder();

        $token = $this->link->createToken($order, time() + 3600);

        self::assertSame((int) $order->getId(), (int) $this->link->findOrderForToken($token)?->getId());
    }

    public function testAnExpiredTokenOpensNothing(): void
    {
        $token = $this->link->createToken($this->unpaidOrder(), time() - 1);

        self::assertNull($this->link->findOrderForToken($token));
    }

    public function testATamperedTokenOpensNothing(): void
    {
        $order = $this->unpaidOrder();
        $other = $this->unpaidOrder();
        [, $expiresAt, $signature] = explode('.', $this->link->createToken($order, time() + 3600));

        self::assertNull($this->link->findOrderForToken($other->getId().'.'.$expiresAt.'.'.$signature));
        self::assertNull($this->link->findOrderForToken($order->getId().'.'.($expiresAt + 1).'.'.$signature));
        self::assertNull($this->link->findOrderForToken('not-a-token'));
    }

    public function testATokenDiesOnceTheOrderIsPaidOrTheAddressChanges(): void
    {
        $paid = $this->unpaidOrder();
        $paidToken = $this->link->createToken($paid, time() + 3600);
        $paid->setStatusId((int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId())->save();

        $moved = $this->unpaidOrder();
        $movedToken = $this->link->createToken($moved, time() + 3600);
        $moved->getCustomer()->setEmail('someone-else-'.$moved->getId().'@example.com')->save();

        self::assertNull($this->link->findOrderForToken($paidToken));
        self::assertNull($this->link->findOrderForToken($movedToken));
    }

    private function unpaidOrder(): Order
    {
        return $this->factory->order(null, ['postage' => 20, 'statusCode' => OrderStatus::CODE_NOT_PAID]);
    }
}
