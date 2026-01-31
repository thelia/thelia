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

namespace Thelia\Tests\Unit\Domain\Catalog\Product;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\DTO\ProductCreateDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductFeatureDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductSeoDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductUpdateDTO;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Product;

class ProductFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private ProductFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new ProductFacade($this->dispatcher);
    }

    public function testCreate(): void
    {
        $dto = new ProductCreateDTO(
            ref: 'TEST-001',
            title: 'Test Product',
            locale: 'en_US',
            defaultCategoryId: 1,
            visible: true,
            virtual: false,
            basePrice: 99.99,
            baseWeight: 1.5,
            taxRuleId: 1,
            currencyId: 1,
            baseQuantity: 100,
            templateId: 2,
        );

        $product = $this->createProductMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ProductCreateEvent $event) use ($product) {
                    self::assertSame('TEST-001', $event->getRef());
                    self::assertSame('Test Product', $event->getTitle());
                    self::assertSame('en_US', $event->getLocale());
                    self::assertSame(1, $event->getDefaultCategory());
                    self::assertTrue($event->getVisible());
                    self::assertFalse($event->getVirtual());
                    self::assertSame(99.99, $event->getBasePrice());
                    self::assertSame(1.5, $event->getBaseWeight());
                    self::assertSame(1, $event->getTaxRuleId());
                    self::assertSame(1, $event->getCurrencyId());
                    self::assertSame(100, $event->getBaseQuantity());
                    self::assertSame(2, $event->getTemplateId());

                    $event->setProduct($product);

                    return true;
                }),
                TheliaEvents::PRODUCT_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($product, $result);
    }

    public function testCreateMinimal(): void
    {
        $dto = new ProductCreateDTO(
            ref: 'MINIMAL-001',
            title: 'Minimal Product',
            locale: 'fr_FR',
            defaultCategoryId: 5,
        );

        $product = $this->createProductMock(11);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ProductCreateEvent $event) use ($product) {
                    self::assertSame('MINIMAL-001', $event->getRef());
                    self::assertSame('Minimal Product', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertSame(5, $event->getDefaultCategory());
                    self::assertTrue($event->getVisible());
                    self::assertFalse($event->getVirtual());
                    self::assertNull($event->getBasePrice());
                    self::assertNull($event->getBaseWeight());

                    $event->setProduct($product);

                    return true;
                }),
                TheliaEvents::PRODUCT_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($product, $result);
    }

    public function testProductCreateDTOToArray(): void
    {
        $dto = new ProductCreateDTO(
            ref: 'TEST-REF',
            title: 'Test Title',
            locale: 'en_US',
            defaultCategoryId: 10,
            visible: false,
            virtual: true,
            basePrice: 50.0,
            baseWeight: 2.0,
            taxRuleId: 3,
            currencyId: 2,
            baseQuantity: 50,
            templateId: 5,
        );

        $array = $dto->toArray();

        $this->assertSame('TEST-REF', $array['ref']);
        $this->assertSame('Test Title', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(10, $array['default_category']);
        $this->assertFalse($array['visible']);
        $this->assertTrue($array['virtual']);
        $this->assertSame(50.0, $array['price']);
        $this->assertSame(2.0, $array['weight']);
        $this->assertSame(3, $array['tax_rule']);
        $this->assertSame(2, $array['currency']);
        $this->assertSame(50, $array['quantity']);
        $this->assertSame(5, $array['template_id']);
    }

    public function testProductUpdateDTOToArray(): void
    {
        $dto = new ProductUpdateDTO(
            ref: 'UPD-REF',
            title: 'Updated Title',
            locale: 'fr_FR',
            defaultCategoryId: 15,
            visible: true,
            virtual: false,
            chapo: 'Short description',
            description: 'Full description',
            postscriptum: 'Postscriptum text',
            brandId: 7,
            virtualDocumentId: 99,
        );

        $array = $dto->toArray();

        $this->assertSame('UPD-REF', $array['ref']);
        $this->assertSame('Updated Title', $array['title']);
        $this->assertSame('fr_FR', $array['locale']);
        $this->assertSame(15, $array['default_category']);
        $this->assertTrue($array['visible']);
        $this->assertFalse($array['virtual']);
        $this->assertSame('Short description', $array['chapo']);
        $this->assertSame('Full description', $array['description']);
        $this->assertSame('Postscriptum text', $array['postscriptum']);
        $this->assertSame(7, $array['brand_id']);
        $this->assertSame(99, $array['virtual_document_id']);
    }

    public function testProductSeoDTOToArray(): void
    {
        $dto = new ProductSeoDTO(
            locale: 'en_US',
            url: 'my-product-url',
            metaTitle: 'SEO Title',
            metaDescription: 'SEO Description',
            metaKeywords: 'keyword1, keyword2',
        );

        $array = $dto->toArray();

        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('my-product-url', $array['url']);
        $this->assertSame('SEO Title', $array['meta_title']);
        $this->assertSame('SEO Description', $array['meta_description']);
        $this->assertSame('keyword1, keyword2', $array['meta_keywords']);
    }

    public function testProductFeatureDTOToArray(): void
    {
        $dto = new ProductFeatureDTO(
            featureId: 5,
            featureValue: 'Custom Value',
            isTextValue: true,
            locale: 'fr_FR',
        );

        $array = $dto->toArray();

        $this->assertSame(5, $array['feature_id']);
        $this->assertSame('Custom Value', $array['feature_value']);
        $this->assertTrue($array['is_text_value']);
        $this->assertSame('fr_FR', $array['locale']);
    }

    public function testProductFeatureDTOWithIntValue(): void
    {
        $dto = new ProductFeatureDTO(
            featureId: 3,
            featureValue: 42,
            isTextValue: false,
        );

        $array = $dto->toArray();

        $this->assertSame(3, $array['feature_id']);
        $this->assertSame(42, $array['feature_value']);
        $this->assertFalse($array['is_text_value']);
        $this->assertNull($array['locale']);
    }

    public function testProductCreateDTODefaultValues(): void
    {
        $dto = new ProductCreateDTO(
            ref: 'TEST',
            title: 'Title',
            locale: 'en_US',
            defaultCategoryId: 1,
        );

        $this->assertTrue($dto->visible);
        $this->assertFalse($dto->virtual);
        $this->assertNull($dto->basePrice);
        $this->assertNull($dto->baseWeight);
        $this->assertNull($dto->taxRuleId);
        $this->assertNull($dto->currencyId);
        $this->assertNull($dto->baseQuantity);
        $this->assertNull($dto->templateId);
    }

    public function testProductUpdateDTODefaultValues(): void
    {
        $dto = new ProductUpdateDTO(
            ref: 'TEST',
            title: 'Title',
            locale: 'en_US',
            defaultCategoryId: 1,
        );

        $this->assertTrue($dto->visible);
        $this->assertFalse($dto->virtual);
        $this->assertNull($dto->chapo);
        $this->assertNull($dto->description);
        $this->assertNull($dto->postscriptum);
        $this->assertNull($dto->brandId);
        $this->assertNull($dto->virtualDocumentId);
    }

    public function testProductSeoDTODefaultValues(): void
    {
        $dto = new ProductSeoDTO(locale: 'en_US');

        $this->assertSame('en_US', $dto->locale);
        $this->assertNull($dto->url);
        $this->assertNull($dto->metaTitle);
        $this->assertNull($dto->metaDescription);
        $this->assertNull($dto->metaKeywords);
    }

    public function testProductFeatureDTODefaultValues(): void
    {
        $dto = new ProductFeatureDTO(
            featureId: 1,
            featureValue: 10,
        );

        $this->assertFalse($dto->isTextValue);
        $this->assertNull($dto->locale);
    }

    private function createProductMock(int $id): MockObject&Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }
}
