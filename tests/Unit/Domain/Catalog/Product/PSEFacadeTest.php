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
use Thelia\Core\Event\Product\ProductCombinationGenerationEvent;
use Thelia\Core\Event\ProductSaleElement\ProductSaleElementCreateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Product\DTO\CombinationGenerationDTO;
use Thelia\Domain\Catalog\Product\DTO\PSECreateDTO;
use Thelia\Domain\Catalog\Product\DTO\PSEUpdateDTO;
use Thelia\Domain\Catalog\Product\DTO\ProductWithPSECreateDTO;
use Thelia\Domain\Catalog\Product\PSEFacade;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

class PSEFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private PSEFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new PSEFacade($this->dispatcher);
    }

    public function testPSECreateDTOToArray(): void
    {
        $dto = new PSECreateDTO(
            currencyId: 1,
            attributeAvIds: [10, 20, 30],
        );

        $array = $dto->toArray();

        $this->assertSame(1, $array['currency_id']);
        $this->assertSame([10, 20, 30], $array['attribute_av_ids']);
    }

    public function testPSECreateDTODefaultValues(): void
    {
        $dto = new PSECreateDTO(currencyId: 2);

        $this->assertSame(2, $dto->currencyId);
        $this->assertSame([], $dto->attributeAvIds);
    }

    public function testPSEUpdateDTOToArray(): void
    {
        $dto = new PSEUpdateDTO(
            reference: 'REF-001',
            price: 99.99,
            currencyId: 1,
            weight: 1.5,
            quantity: 100.0,
            salePrice: 79.99,
            onSale: true,
            isNew: true,
            isDefault: true,
            eanCode: '1234567890123',
            taxRuleId: 5,
            fromDefaultCurrency: false,
        );

        $array = $dto->toArray();

        $this->assertSame('REF-001', $array['reference']);
        $this->assertSame(99.99, $array['price']);
        $this->assertSame(1, $array['currency_id']);
        $this->assertSame(1.5, $array['weight']);
        $this->assertSame(100.0, $array['quantity']);
        $this->assertSame(79.99, $array['sale_price']);
        $this->assertTrue($array['on_sale']);
        $this->assertTrue($array['is_new']);
        $this->assertTrue($array['is_default']);
        $this->assertSame('1234567890123', $array['ean_code']);
        $this->assertSame(5, $array['tax_rule_id']);
        $this->assertFalse($array['from_default_currency']);
    }

    public function testPSEUpdateDTODefaultValues(): void
    {
        $dto = new PSEUpdateDTO(
            reference: 'REF',
            price: 10.0,
            currencyId: 1,
        );

        $this->assertSame(0.0, $dto->weight);
        $this->assertSame(0.0, $dto->quantity);
        $this->assertSame(0.0, $dto->salePrice);
        $this->assertFalse($dto->onSale);
        $this->assertFalse($dto->isNew);
        $this->assertFalse($dto->isDefault);
        $this->assertNull($dto->eanCode);
        $this->assertSame(0, $dto->taxRuleId);
        $this->assertFalse($dto->fromDefaultCurrency);
    }

    public function testCombinationGenerationDTOToArray(): void
    {
        $combinations = [
            [1, 2],
            [1, 3],
            [4, 2],
            [4, 3],
        ];

        $dto = new CombinationGenerationDTO(
            currencyId: 1,
            combinations: $combinations,
            reference: 'BASE-REF',
            price: 50.0,
            weight: 2.0,
            quantity: 10.0,
            salePrice: 40.0,
            onSale: true,
            isNew: false,
            eanCode: '0000000000000',
        );

        $array = $dto->toArray();

        $this->assertSame(1, $array['currency_id']);
        $this->assertSame($combinations, $array['combinations']);
        $this->assertSame('BASE-REF', $array['reference']);
        $this->assertSame(50.0, $array['price']);
        $this->assertSame(2.0, $array['weight']);
        $this->assertSame(10.0, $array['quantity']);
        $this->assertSame(40.0, $array['sale_price']);
        $this->assertTrue($array['on_sale']);
        $this->assertFalse($array['is_new']);
        $this->assertSame('0000000000000', $array['ean_code']);
    }

    public function testCombinationGenerationDTODefaultValues(): void
    {
        $dto = new CombinationGenerationDTO(
            currencyId: 1,
            combinations: [[1, 2]],
        );

        $this->assertNull($dto->reference);
        $this->assertNull($dto->price);
        $this->assertNull($dto->weight);
        $this->assertNull($dto->quantity);
        $this->assertNull($dto->salePrice);
        $this->assertFalse($dto->onSale);
        $this->assertFalse($dto->isNew);
        $this->assertNull($dto->eanCode);
    }

    public function testProductWithPSECreateDTOToArray(): void
    {
        $dto = new ProductWithPSECreateDTO(
            ref: 'FULL-001',
            title: 'Full Product',
            locale: 'en_US',
            defaultCategoryId: 5,
            price: 199.99,
            currencyId: 1,
            visible: true,
            virtual: false,
            weight: 3.5,
            quantity: 50,
            taxRuleId: 2,
            templateId: 10,
            salePrice: 149.99,
            onSale: true,
            isNew: true,
            eanCode: '9876543210123',
        );

        $array = $dto->toArray();

        $this->assertSame('FULL-001', $array['ref']);
        $this->assertSame('Full Product', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame(5, $array['default_category']);
        $this->assertSame(199.99, $array['price']);
        $this->assertSame(1, $array['currency_id']);
        $this->assertTrue($array['visible']);
        $this->assertFalse($array['virtual']);
        $this->assertSame(3.5, $array['weight']);
        $this->assertSame(50, $array['quantity']);
        $this->assertSame(2, $array['tax_rule']);
        $this->assertSame(10, $array['template_id']);
        $this->assertSame(149.99, $array['sale_price']);
        $this->assertTrue($array['on_sale']);
        $this->assertTrue($array['is_new']);
        $this->assertSame('9876543210123', $array['ean_code']);
    }

    public function testProductWithPSECreateDTODefaultValues(): void
    {
        $dto = new ProductWithPSECreateDTO(
            ref: 'SIMPLE',
            title: 'Simple Product',
            locale: 'fr_FR',
            defaultCategoryId: 1,
            price: 10.0,
            currencyId: 1,
        );

        $this->assertTrue($dto->visible);
        $this->assertFalse($dto->virtual);
        $this->assertNull($dto->weight);
        $this->assertNull($dto->quantity);
        $this->assertNull($dto->taxRuleId);
        $this->assertNull($dto->templateId);
        $this->assertNull($dto->salePrice);
        $this->assertFalse($dto->onSale);
        $this->assertFalse($dto->isNew);
        $this->assertNull($dto->eanCode);
    }

    public function testProductWithPSECreateDTOToProductCreateDTO(): void
    {
        $dto = new ProductWithPSECreateDTO(
            ref: 'TEST',
            title: 'Test Product',
            locale: 'en_US',
            defaultCategoryId: 1,
            price: 100.0,
            currencyId: 1,
            weight: 2.5,
            quantity: 25,
            taxRuleId: 3,
            templateId: 5,
        );

        $productCreateDTO = $dto->toProductCreateDTO();

        $this->assertSame('TEST', $productCreateDTO->ref);
        $this->assertSame('Test Product', $productCreateDTO->title);
        $this->assertSame('en_US', $productCreateDTO->locale);
        $this->assertSame(1, $productCreateDTO->defaultCategoryId);
        $this->assertSame(100.0, $productCreateDTO->basePrice);
        $this->assertSame(1, $productCreateDTO->currencyId);
        $this->assertSame(2.5, $productCreateDTO->baseWeight);
        $this->assertSame(25, $productCreateDTO->baseQuantity);
        $this->assertSame(3, $productCreateDTO->taxRuleId);
        $this->assertSame(5, $productCreateDTO->templateId);
    }

    public function testProductWithPSECreateDTOToPSEUpdateDTO(): void
    {
        $dto = new ProductWithPSECreateDTO(
            ref: 'TEST',
            title: 'Test Product',
            locale: 'en_US',
            defaultCategoryId: 1,
            price: 100.0,
            currencyId: 1,
            weight: 2.5,
            quantity: 25,
            taxRuleId: 3,
            salePrice: 80.0,
            onSale: true,
            isNew: true,
            eanCode: '1111111111111',
        );

        $pseUpdateDTO = $dto->toPSEUpdateDTO();

        $this->assertSame('TEST', $pseUpdateDTO->reference);
        $this->assertSame(100.0, $pseUpdateDTO->price);
        $this->assertSame(1, $pseUpdateDTO->currencyId);
        $this->assertSame(2.5, $pseUpdateDTO->weight);
        $this->assertSame(25.0, $pseUpdateDTO->quantity);
        $this->assertSame(80.0, $pseUpdateDTO->salePrice);
        $this->assertTrue($pseUpdateDTO->onSale);
        $this->assertTrue($pseUpdateDTO->isNew);
        $this->assertTrue($pseUpdateDTO->isDefault);
        $this->assertSame('1111111111111', $pseUpdateDTO->eanCode);
        $this->assertSame(3, $pseUpdateDTO->taxRuleId);
    }

    private function createProductMock(int $id): MockObject&Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function createPSEMock(int $id): MockObject&ProductSaleElements
    {
        $pse = $this->createMock(ProductSaleElements::class);
        $pse->method('getId')->willReturn($id);

        return $pse;
    }
}
