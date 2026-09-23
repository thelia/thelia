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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * Content::getDefaultFolderId() and Product::getDefaultCategoryId() return 0,
 * not null, when no default link exists. The attribute accessors must treat
 * that 0 as "no parent" and return an empty string, like every other guard in
 * the service, instead of querying folder/category 0 and ending up in the
 * NotFoundHttpException of dataAccessWithI18n().
 */
final class AttributeAccessServiceTest extends IntegrationTestCase
{
    private AttributeAccessService $attributeAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAccess = static::getContainer()->get(AttributeAccessService::class);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testFolderAttributeIsEmptyForContentWithoutDefaultFolder(): void
    {
        $content = new Content();
        $content->setVisible(1);
        $content->setLocale('en_US');
        $content->setTitle('Content without folder');
        $content->save($this->getPropelConnection());

        self::assertSame(0, $content->getDefaultFolderId());

        $this->setRequestParam('content_id', $content->getId());

        self::assertSame('', $this->attributeAccess->attributeFolder('TITLE'));
    }

    public function testCategoryAttributeIsEmptyForProductWithoutDefaultCategory(): void
    {
        $factory = $this->createFixtureFactory();

        $product = new Product();
        $product->setRef('PROD-WITHOUT-CATEGORY');
        $product->setVisible(1);
        $product->setPosition(1);
        $product->setTaxRuleId($factory->taxRule()->getId());
        $product->setLocale('en_US');
        $product->setTitle('Product without category');
        $product->save($this->getPropelConnection());

        self::assertSame(0, $product->getDefaultCategoryId());

        $this->setRequestParam('product_id', $product->getId());

        self::assertSame('', $this->attributeAccess->attributeCategory('TITLE'));
    }

    public function testCartAttributesExposeTheVatExemptionState(): void
    {
        $factory = $this->createFixtureFactory();
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        $shopCountry = $factory->country(['isocode' => 'FR', 'isoalpha2' => 'FR', 'isoalpha3' => 'FRX', 'shopCountry' => true]);
        ConfigQuery::write('store_country', (string) $shopCountry->getId());

        $title = $factory->customerTitle();
        $buyerCountry = $factory->country(['isocode' => 'BE', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEX']);
        $invoiceAddress = $factory->cartAddress(null, $buyerCountry, $title);
        $invoiceAddress
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-10 days'))
            ->save($this->getPropelConnection());

        $cart = $factory->cart();
        $cart->setAddressInvoiceId($invoiceAddress->getId())->save($this->getPropelConnection());

        static::getContainer()->get('request_stack')->getCurrentRequest()->getSession()->setSessionCart($cart);

        self::assertTrue($this->attributeAccess->attributeCart('is_vat_exempted'));
        self::assertSame('BE0123456789', $this->attributeAccess->attributeCart('invoice_vat_number'));
    }

    public function testOrderAttributesExposeTheFrozenVatExemptionState(): void
    {
        $order = $this->createFixtureFactory()->order();
        $order->getOrderAddressRelatedByInvoiceOrderAddressId()
            ->setVatExempted(1)
            ->setVatNumber('BE0123456789')
            ->save($this->getPropelConnection());

        static::getContainer()->get('request_stack')->getCurrentRequest()->getSession()->setOrder($order);

        self::assertTrue($this->attributeAccess->orderDataAccess('vat_exempted'));
        self::assertSame('BE0123456789', $this->attributeAccess->orderDataAccess('invoice_vat_number'));
    }

    private function setRequestParam(string $key, mixed $value): void
    {
        static::getContainer()->get('request_stack')->getCurrentRequest()->attributes->set($key, $value);
    }
}
