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

namespace Thelia\Tests\Integration\Test;

use PHPUnit\Framework\Attributes\Depends;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;

/**
 * An integration test starts from a clean memory, whatever ran before it: a test that left
 * a static memo behind (a unit test, or a test that died before its tearDown()) must not
 * decide the result of the next one when the suites run in a random order.
 */
final class IntegrationTestCaseIsolationTest extends IntegrationTestCase
{
    private bool $leaveAMemoBehind = false;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->leaveAMemoBehind) {
            (new \ReflectionProperty(Currency::class, 'defaultCurrency'))->setValue(null, new Currency());
        }
    }

    public function testATestThatLeavesAStaleDefaultCurrencyBehind(): void
    {
        $this->leaveAMemoBehind = true;

        self::assertNull($this->defaultCurrencyMemo());
    }

    #[Depends('testATestThatLeavesAStaleDefaultCurrencyBehind')]
    public function testTheNextTestDoesNotSeeIt(): void
    {
        self::assertNull($this->defaultCurrencyMemo(), 'The memo of the test before leaked into this one.');
    }

    private function defaultCurrencyMemo(): ?Currency
    {
        return (new \ReflectionProperty(Currency::class, 'defaultCurrency'))->getValue();
    }
}
