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

namespace Thelia\Tests\Unit\Domain\Promotion\Coupon\Type;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;

/**
 * The offered product and its category, as the back office stores them (text)
 * and shows them again in the coupon form.
 */
final class FreeProductTest extends TestCase
{
    public function testTheFormShowsTheStoredOfferedProductAndCategoryAsNumbers(): void
    {
        $parameters = $this->formParametersAfterReading([
            FreeProduct::OFFERED_PRODUCT_ID => '12',
            FreeProduct::OFFERED_CATEGORY_ID => '3',
        ]);

        self::assertSame(12, $parameters['offered_product_value']);
        self::assertSame(3, $parameters['offered_category_value']);
    }

    public function testTheFormShowsNoOfferedProductNorCategoryWhenNoneIsStored(): void
    {
        $parameters = $this->formParametersAfterReading([
            FreeProduct::OFFERED_PRODUCT_ID => '0',
            FreeProduct::OFFERED_CATEGORY_ID => '',
        ]);

        self::assertNull($parameters['offered_product_value']);
        self::assertNull($parameters['offered_category_value']);
    }

    /**
     * @param array<string, mixed> $effects
     *
     * @return array<string, mixed>
     */
    private function formParametersAfterReading(array $effects): array
    {
        $rendered = [];
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('render')->willReturnCallback(
            static function (string $template, array $parameters) use (&$rendered): string {
                $rendered = $parameters;

                return '';
            },
        );

        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($this->createStub(Translator::class));
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getParser')->willReturn($parser);

        $coupon = new FreeProduct($facade);
        $coupon->setFieldsValue($effects);
        $coupon->drawBackOfficeInputs();

        return $rendered;
    }
}
