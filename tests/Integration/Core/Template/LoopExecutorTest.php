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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Element\Exception\ElementNotFoundException;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

final class LoopExecutorTest extends IntegrationTestCase
{
    public function testExecuteRunsAKnownLoopAndReturnsItsRows(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        // The product loop joins the i18n title for a given language; give the fixture one
        // so the row is returned, and target that language explicitly (no HTTP request here).
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $product->setLocale($lang->getLocale())->setTitle('LoopExecutor Test Product')->save();

        $result = $this->getLoopExecutor()->execute('product', [
            'id' => $product->getId(),
            'lang' => $lang->getId(),
        ]);

        self::assertInstanceOf(LoopResult::class, $result);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row->getVarVal();
        }

        self::assertCount(1, $rows);
        self::assertSame((string) $product->getId(), (string) $rows[0]['ID']);
        self::assertSame($product->getRef(), $rows[0]['REF']);
    }

    public function testExecuteNormalizesUnderscoreTypeToKebabCase(): void
    {
        // "order_product" must resolve to the same loop as "order-product".
        // With order=0 the loop yields no row, but the type must still be found
        // (no ElementNotFoundException), which proves the underscore normalization.
        $result = $this->getLoopExecutor()->execute('order_product', ['order' => 0]);

        self::assertInstanceOf(LoopResult::class, $result);
        self::assertSame(0, iterator_count($result));
    }

    public function testExecuteThrowsOnUnknownLoopType(): void
    {
        $this->expectException(ElementNotFoundException::class);

        $this->getLoopExecutor()->execute('this-loop-does-not-exist');
    }

    private function getLoopExecutor(): LoopExecutor
    {
        return $this->getService(LoopExecutor::class);
    }
}
