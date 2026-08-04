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
use Thelia\Model\Content;
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

    private function setRequestParam(string $key, mixed $value): void
    {
        static::getContainer()->get('request_stack')->getCurrentRequest()->attributes->set($key, $value);
    }
}
