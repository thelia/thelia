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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Scope;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Scope\ScopeCriterionResolverInterface;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Model\CatalogPriceRule;

/**
 * The predicate a rule compiles to, before anything touches the database: what the
 * preview runs, and what the materializer writes down.
 */
final class ScopeMaterializerTest extends TestCase
{
    public function testEveryCriterionTypeNoResolverAnswersForIsReported(): void
    {
        $materializer = new ScopeMaterializer([$this->resolver('brand', 'p.brand_id IN (4)')]);

        $predicate = $materializer->predicateFor(new CatalogPriceRule(), [
            'module_a' => [1],
            'brand' => [4],
            'module_b' => [2],
        ]);

        self::assertSame(['0 = 1', ['module_a', 'module_b']], $predicate);
    }

    public function testTheTypesOfARuleCombineWithAnd(): void
    {
        $materializer = new ScopeMaterializer([
            $this->resolver('brand', 'p.brand_id IN (4)'),
            $this->resolver('template', 'p.template_id IN (2)'),
        ]);

        $predicate = $materializer->predicateFor(new CatalogPriceRule(), ['brand' => [4], 'template' => [2], 'category' => []]);

        self::assertSame(['p.brand_id IN (4) AND p.template_id IN (2)', []], $predicate);
    }

    private function resolver(string $type, string $predicate): ScopeCriterionResolverInterface
    {
        $resolver = $this->createStub(ScopeCriterionResolverInterface::class);
        $resolver->method('type')->willReturn($type);
        $resolver->method('predicate')->willReturn($predicate);

        return $resolver;
    }
}
