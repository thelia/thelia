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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * CategoryQuery::findAllChildId() memoises its tree walk in a function static,
 * which lives for the whole process: the same category walked with another depth
 * must not be served the answer of an earlier call.
 */
final class CategoryChildIdDepthTest extends IntegrationTestCase
{
    public function testAnUnlimitedWalkIsNotServedTheAnswerOfADepthLimitedOne(): void
    {
        [$root, $child, $grandChild] = $this->createThreeLevelTree();

        self::assertSame([$child->getId()], CategoryQuery::findAllChildId($root->getId(), 2));

        self::assertSame(
            [$child->getId(), $grandChild->getId()],
            CategoryQuery::findAllChildId($root->getId()),
            'The whole tree is expected, not the two levels the previous call asked for.',
        );
    }

    public function testADepthLimitedWalkIsNotServedTheAnswerOfAnUnlimitedOne(): void
    {
        [$root, $child, $grandChild] = $this->createThreeLevelTree();

        self::assertSame(
            [$child->getId(), $grandChild->getId()],
            CategoryQuery::findAllChildId($root->getId()),
        );

        self::assertSame(
            [$child->getId()],
            CategoryQuery::findAllChildId($root->getId(), 2),
            'The depth limit still applies once the tree has been walked without one.',
        );
    }

    /**
     * @return array{Category, Category, Category}
     */
    private function createThreeLevelTree(): array
    {
        $factory = $this->createFixtureFactory();

        $root = $factory->category();
        $child = $factory->category(['parent' => $root->getId()]);
        $grandChild = $factory->category(['parent' => $child->getId()]);

        return [$root, $child, $grandChild];
    }
}
