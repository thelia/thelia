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

namespace Thelia\Tests\Unit\Domain\Catalog\Tax;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Tax\TaxEvent;
use Thelia\Core\Event\Tax\TaxRuleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Tax\DTO\TaxCreateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxRuleCreateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxRuleUpdateDTO;
use Thelia\Domain\Catalog\Tax\DTO\TaxUpdateDTO;
use Thelia\Domain\Catalog\Tax\TaxFacade;
use Thelia\Model\Tax;
use Thelia\Model\TaxRule;

class TaxFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private TaxFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->facade = new TaxFacade($this->dispatcher);
    }

    public function testCreateTax(): void
    {
        $dto = new TaxCreateDTO(
            title: 'TVA 20%',
            locale: 'fr_FR',
            type: 'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType',
            description: 'Standard VAT rate',
            requirements: ['amount' => 20],
        );

        $tax = $this->createTaxMock(10);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (TaxEvent $event) use ($tax) {
                    self::assertSame('TVA 20%', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertSame('Thelia\\TaxEngine\\TaxType\\FixAmountTaxType', $event->getType());
                    self::assertSame('Standard VAT rate', $event->getDescription());
                    self::assertSame(['amount' => 20], $event->getRequirements());

                    $event->setTax($tax);

                    return true;
                }),
                TheliaEvents::TAX_CREATE
            );

        $result = $this->facade->createTax($dto);

        $this->assertSame($tax, $result);
    }

    public function testCreateTaxMinimal(): void
    {
        $dto = new TaxCreateDTO(
            title: 'TVA Réduite',
            locale: 'fr_FR',
            type: 'Thelia\\TaxEngine\\TaxType\\PercentageTaxType',
        );

        $tax = $this->createTaxMock(11);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (TaxEvent $event) use ($tax) {
                    self::assertSame('TVA Réduite', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertNull($event->getDescription());
                    self::assertSame([], $event->getRequirements());

                    $event->setTax($tax);

                    return true;
                }),
                TheliaEvents::TAX_CREATE
            );

        $result = $this->facade->createTax($dto);

        $this->assertSame($tax, $result);
    }

    public function testCreateTaxRule(): void
    {
        $dto = new TaxRuleCreateDTO(
            title: 'France Metropolitan',
            locale: 'fr_FR',
            description: 'Tax rule for France',
        );

        $taxRule = $this->createTaxRuleMock(5);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (TaxRuleEvent $event) use ($taxRule) {
                    self::assertSame('France Metropolitan', $event->getTitle());
                    self::assertSame('fr_FR', $event->getLocale());
                    self::assertSame('Tax rule for France', $event->getDescription());

                    $event->setTaxRule($taxRule);

                    return true;
                }),
                TheliaEvents::TAX_RULE_CREATE
            );

        $result = $this->facade->createTaxRule($dto);

        $this->assertSame($taxRule, $result);
    }

    public function testTaxCreateDTOToArray(): void
    {
        $dto = new TaxCreateDTO(
            title: 'TVA 20%',
            locale: 'fr_FR',
            type: 'PercentageTaxType',
            description: 'Standard VAT rate',
            requirements: ['percent' => 20],
        );

        $array = $dto->toArray();

        $this->assertSame('TVA 20%', $array['title']);
        $this->assertSame('fr_FR', $array['locale']);
        $this->assertSame('PercentageTaxType', $array['type']);
        $this->assertSame('Standard VAT rate', $array['description']);
        $this->assertSame(['percent' => 20], $array['requirements']);
    }

    public function testTaxCreateDTODefaultValues(): void
    {
        $dto = new TaxCreateDTO(
            title: 'Test',
            locale: 'en_US',
            type: 'FixedTax',
        );

        $this->assertNull($dto->description);
        $this->assertSame([], $dto->requirements);
    }

    public function testTaxUpdateDTOToArray(): void
    {
        $dto = new TaxUpdateDTO(
            title: 'Updated Tax',
            locale: 'en_US',
            type: 'PercentageTaxType',
            description: 'Updated description',
            requirements: ['percent' => 25],
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Tax', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('PercentageTaxType', $array['type']);
        $this->assertSame('Updated description', $array['description']);
        $this->assertSame(['percent' => 25], $array['requirements']);
    }

    public function testTaxUpdateDTODefaultValues(): void
    {
        $dto = new TaxUpdateDTO(
            title: 'Test',
            locale: 'en_US',
            type: 'FixedTax',
        );

        $this->assertNull($dto->description);
        $this->assertSame([], $dto->requirements);
    }

    public function testTaxRuleCreateDTOToArray(): void
    {
        $dto = new TaxRuleCreateDTO(
            title: 'France Tax Rule',
            locale: 'fr_FR',
            description: 'Tax rule for France',
        );

        $array = $dto->toArray();

        $this->assertSame('France Tax Rule', $array['title']);
        $this->assertSame('fr_FR', $array['locale']);
        $this->assertSame('Tax rule for France', $array['description']);
    }

    public function testTaxRuleCreateDTODefaultValues(): void
    {
        $dto = new TaxRuleCreateDTO(
            title: 'Test',
            locale: 'en_US',
        );

        $this->assertNull($dto->description);
    }

    public function testTaxRuleUpdateDTOToArray(): void
    {
        $dto = new TaxRuleUpdateDTO(
            title: 'Updated Tax Rule',
            locale: 'en_US',
            description: 'Updated description',
            countryList: [1, 2, 3],
            countryDeletedList: [4],
            taxList: [10, 11],
        );

        $array = $dto->toArray();

        $this->assertSame('Updated Tax Rule', $array['title']);
        $this->assertSame('en_US', $array['locale']);
        $this->assertSame('Updated description', $array['description']);
        $this->assertSame([1, 2, 3], $array['country_list']);
        $this->assertSame([4], $array['country_deleted_list']);
        $this->assertSame([10, 11], $array['tax_list']);
    }

    public function testTaxRuleUpdateDTODefaultValues(): void
    {
        $dto = new TaxRuleUpdateDTO(
            title: 'Test',
            locale: 'en_US',
        );

        $this->assertNull($dto->description);
        $this->assertSame([], $dto->countryList);
        $this->assertSame([], $dto->countryDeletedList);
        $this->assertSame([], $dto->taxList);
    }

    private function createTaxMock(int $id): MockObject&Tax
    {
        $tax = $this->createMock(Tax::class);
        $tax->method('getId')->willReturn($id);

        return $tax;
    }

    private function createTaxRuleMock(int $id): MockObject&TaxRule
    {
        $taxRule = $this->createMock(TaxRule::class);
        $taxRule->method('getId')->willReturn($id);

        return $taxRule;
    }
}
