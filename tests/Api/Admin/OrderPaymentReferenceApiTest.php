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

namespace Thelia\Tests\Api\Admin;

use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;

/**
 * Once money moved on an order, its payment journal names the provider reference and the
 * payment module of each movement. The transaction reference and the payment module of the
 * order are then no longer changed through the admin API: a refund or a release would go
 * to the reference of another order, through another module.
 */
final class OrderPaymentReferenceApiTest extends ApiTestCase
{
    public function testTheTransactionReferenceOfAnOrderWithAPaymentJournalIsNotChangedByHand(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->orderWithOneLine('pi_original', journal: true);

        $body = $this->bodyOf($token, $order);
        $body['transactionRef'] = 'pi_of_another_order';
        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $body, $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('pi_original', $this->reread($order)->getTransactionRef());
    }

    public function testThePaymentModuleOfAnOrderWithAPaymentJournalIsNotChangedByHand(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->orderWithOneLine('pi_original', journal: true);
        $other = ModuleQuery::create()->findOneByCode('FreeOrder');
        self::assertNotNull($other);

        $body = $this->bodyOf($token, $order);
        $body['paymentModule'] = '/api/admin/modules/'.$other->getId();
        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $body, $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($order->getPaymentModuleId(), $this->reread($order)->getPaymentModuleId());
    }

    public function testAnOrderWithAPaymentJournalStillTakesItsOtherFields(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->orderWithOneLine('pi_original', journal: true);

        $body = $this->bodyOf($token, $order);
        $body['deliveryRef'] = 'PARCEL-42';
        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $body, $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('PARCEL-42', $this->reread($order)->getDeliveryRef());
    }

    public function testAnOrderWithoutPaymentJournalStillTakesItsTransactionReference(): void
    {
        $token = $this->authenticateAsAdmin();
        $order = $this->orderWithOneLine(null);

        $body = $this->bodyOf($token, $order);
        $body['transactionRef'] = 'pi_set_by_an_integration';
        $response = $this->jsonRequest('PUT', '/api/admin/orders/'.$order->getId(), $body, $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('pi_set_by_an_integration', $this->reread($order)->getTransactionRef());
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyOf(string $token, Order $order): array
    {
        $response = $this->jsonRequest('GET', '/api/admin/orders/'.$order->getId(), [], $token);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function reread(Order $order): Order
    {
        OrderTableMap::clearInstancePool();
        $reread = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reread);

        return $reread;
    }

    private function orderWithOneLine(?string $transactionRef, bool $journal = false): Order
    {
        $order = $this->createFixtureFactory()->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $order->setTransactionRef($transactionRef)->save();

        if ($journal) {
            $this->getService(PaymentTransactionRecorder::class)->recordCapture($order, 10, $transactionRef, moduleCode: 'Cheque');
        }

        $orderProduct = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('ref')
            ->setProductSaleElementsRef('pse-ref')
            ->setTitle('Product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $orderProduct->save();
        (new OrderProductTax())
            ->setOrderProductId($orderProduct->getId())
            ->setTitle('VAT')
            ->setAmount('2.000000')
            ->setPromoAmount('2.000000')
            ->save();

        return $order;
    }
}
