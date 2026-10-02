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

namespace Thelia\Tests\Http\Flexy;

use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The url of a hidden product still resolves to the `product` view before the page turns it
 * down. The not found page must then be the one an url leading nowhere gets: rendered with the
 * product view on the request, it carried the product's language versions and breadcrumb, which
 * tells a hidden product apart from one that never existed.
 */
final class HiddenProductPageTest extends WebIntegrationTestCase
{
    private const PRODUCT_URL = 'flexy-hidden-product-page-test.html';

    public function testAHiddenProductAnswersTheSameAsAnUnknownUrl(): void
    {
        $this->client->request('GET', '/'.self::PRODUCT_URL);
        $unknown = $this->client->getResponse();

        $factory = new FixtureFactory($this->getPropelConnection());
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $product->setLocale('en_US')->setTitle('Hidden product page product')->setVisible(0)->save($this->getPropelConnection());
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        $this->client->request('GET', '/'.self::PRODUCT_URL);
        $hidden = $this->client->getResponse();

        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame(404, $hidden->getStatusCode());
        self::assertSame($unknown->getContent(), $hidden->getContent());
    }
}
