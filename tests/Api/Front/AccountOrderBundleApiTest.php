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

use Thelia\Model\OrderProduct;
use Thelia\Test\ApiTestCase;

/**
 * An order made of a pack, as the account of the buyer reads it: the line of the pack, the
 * lines of its components, and what ties the second to the first.
 */
final class AccountOrderBundleApiTest extends ApiTestCase
{
    public function testTheLinesOfABundleCarryTheirTypeAndTheirParent(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $order = $factory->order($customer, ['statusCode' => 'paid']);

        $bundleLine = $factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE, 'title' => 'The pack', 'price' => '540.000000']);
        $componentLine = $factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 'title' => 'The bike', 'price' => '0.000000', 'quantity' => 2.0]);
        $componentLine->setParent($bundleLine->getId())->save($this->getPropelConnection());
        $plainLine = $factory->orderProduct($order, ['title' => 'Alone']);

        $response = $this->jsonRequest('GET', '/api/front/account/orders/'.$order->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        $lines = [];

        foreach (json_decode($response->getContent(), true)['orderProducts'] as $line) {
            $lines[$line['title']] = $line;
        }

        self::assertSame('bundle', $lines['The pack']['lineType']);
        // The API leaves out a null property: a line under no pack has no parent key at all.
        self::assertArrayNotHasKey('parent', $lines['The pack'], 'The line of a pack hangs under no other line.');
        self::assertSame('bundle_component', $lines['The bike']['lineType']);
        self::assertSame($bundleLine->getId(), $lines['The bike']['parent'], 'A component names the line of its pack.');
        self::assertSame('product', $lines['Alone']['lineType']);
        self::assertArrayNotHasKey('parent', $lines['Alone'], 'A line bought alone hangs under no other line.');
        self::assertNotNull($plainLine->getId());
    }
}
