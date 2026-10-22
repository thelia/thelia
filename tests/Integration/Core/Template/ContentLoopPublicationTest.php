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

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Content;
use Thelia\Model\ProductAssociatedContent;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The content loop lists what the shop shows: a visible content held by a default folder
 * that is a draft or still waiting for its date is not on the shop, and listing it would
 * hand out a title and a link that answers 404. A caller asking for the hidden rows, or
 * for every row, reads them as before.
 */
final class ContentLoopPublicationTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    public function testAVisibleContentHeldByAFolderNotOnlineYetIsNotListedAmongTheVisibleOnes(): void
    {
        $shown = $this->factory->content($this->factory->folder(0, ['visible' => 1]), ['visible' => 1]);
        $heldByADraftFolder = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);
        $hidden = $this->factory->content($this->factory->folder(0, ['visible' => 1]), ['visible' => 0]);
        $ids = implode(',', [$shown->getId(), $heldByADraftFolder->getId(), $hidden->getId()]);

        self::assertSame([(int) $shown->getId()], $this->listedIds('content', ['id' => $ids]));
        self::assertSame([(int) $hidden->getId()], $this->listedIds('content', ['id' => $ids, 'visible' => false]), 'the hidden rows are the caller\'s to read');
        self::assertSame(
            [(int) $shown->getId(), (int) $heldByADraftFolder->getId(), (int) $hidden->getId()],
            $this->listedIds('content', ['id' => $ids, 'visible' => '*']),
            'a back office or a module asking for every row reads every row',
        );
    }

    public function testAFolderTakenOfflineKeepsItsContentsListed(): void
    {
        $offline = $this->factory->folder(0, ['visible' => 0]);
        $offline->setUnpublishAt(new \DateTimeImmutable('-1 day'))->save();
        $content = $this->factory->content($offline, ['visible' => 1]);

        self::assertSame([(int) $content->getId()], $this->listedIds('content', ['id' => $content->getId()]), 'as an invisible folder always behaved');
    }

    public function testTheAssociatedContentLoopFollowsTheSameRule(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $shown = $this->factory->content($this->factory->folder(0, ['visible' => 1]), ['visible' => 1]);
        $heldByADraftFolder = $this->factory->content($this->factory->folder(0, ['visible' => 0]), ['visible' => 1]);

        foreach ([$shown, $heldByADraftFolder] as $position => $content) {
            $this->associate($product->getId(), $content, $position + 1);
        }

        self::assertSame([(int) $shown->getId()], $this->listedIds('associated_content', ['product' => $product->getId()], 'CONTENT_ID'));
    }

    private function associate(int $productId, Content $content, int $position): void
    {
        (new ProductAssociatedContent())->setProductId($productId)->setContentId($content->getId())->setPosition($position)->save();
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int> sorted, the order of the loop is not what is tested
     */
    private function listedIds(string $loop, array $arguments, string $idVariable = 'ID'): array
    {
        $ids = [];

        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            $ids[] = (int) $row->getVarVal()[$idVariable];
        }

        sort($ids);

        return $ids;
    }
}
