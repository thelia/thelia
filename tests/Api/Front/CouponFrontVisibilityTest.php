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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Test\ApiTestCase;

/**
 * The front API exposes nothing of the promotions: a customer enters a code through
 * the cart, and no promotion resource is readable from the front.
 */
final class CouponFrontVisibilityTest extends ApiTestCase
{
    #[DataProvider('frontPromotionUris')]
    public function testNoPromotionResourceIsServedToAnAuthenticatedCustomer(string $uriTemplate): void
    {
        $coupon = $this->createFixtureFactory()->coupon(['code' => 'PROMO-'.uniqid()]);

        $response = $this->jsonRequest('GET', self::uri($uriTemplate, $coupon), token: $this->authenticateAsCustomer());

        self::assertSame(404, $response->getStatusCode(), $uriTemplate);
        self::assertStringNotContainsString($coupon->getCode(), (string) $response->getContent());
    }

    #[DataProvider('frontPromotionUris')]
    public function testNoPromotionResourceIsServedToAVisitor(string $uriTemplate): void
    {
        $coupon = $this->createFixtureFactory()->coupon(['code' => 'PROMO-'.uniqid()]);

        $response = $this->jsonRequest('GET', self::uri($uriTemplate, $coupon));

        self::assertSame(404, $response->getStatusCode(), $uriTemplate);
        self::assertStringNotContainsString($coupon->getCode(), (string) $response->getContent());
    }

    public function testACustomerStillReadsThePromotionsOfTheirOwnOrder(): void
    {
        $factory = $this->createFixtureFactory();
        $owner = $factory->customer($factory->customerTitle());
        $order = $factory->order($owner);
        $factory->orderCoupon($order, $factory->coupon(['code' => 'PROMO-'.uniqid()]));

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($owner));

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty(json_decode((string) $response->getContent(), true)['orderCoupons'] ?? []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function frontPromotionUris(): iterable
    {
        yield 'list' => ['/api/front/coupons'];
        yield 'by id' => ['/api/front/coupons/{id}'];
        yield 'by code' => ['/api/front/coupons/{code}/by-code'];
    }

    private static function uri(string $template, \Thelia\Model\Coupon $coupon): string
    {
        return strtr($template, ['{id}' => (string) $coupon->getId(), '{code}' => $coupon->getCode()]);
    }
}
