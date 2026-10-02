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

use Thelia\Test\ApiTestCase;

/**
 * A visitor reads the codes a combination is identified by, never the duplicates the
 * back office is warned about.
 */
final class ProductIdentifiersApiTest extends ApiTestCase
{
    public function testTheFrontReadGivesTheGtinAndThePartNumberButNotTheDuplicates(): void
    {
        $factory = $this->createFixtureFactory();
        $currency = $factory->currency();
        $product = $factory->product($factory->category(), $factory->taxRule(), $currency);
        $combination = $factory->productSaleElement($product, ['eanCode' => '4006381333931', 'mpn' => 'MPN-FRONT']);
        $factory->productSaleElement(
            $factory->product($factory->category(), $factory->taxRule(), $currency),
            ['eanCode' => '4006381333931'],
        );

        $response = $this->jsonRequest('GET', '/api/front/product_sale_elements/'.$combination->getId());

        self::assertJsonResponseSuccessful($response);
        $read = self::decodeJson($response);
        self::assertSame('4006381333931', $read['eanCode']);
        self::assertSame('MPN-FRONT', $read['mpn']);
        self::assertArrayNotHasKey('gtinSharedWith', $read);
    }
}
