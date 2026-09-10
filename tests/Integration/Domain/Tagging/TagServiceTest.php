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

namespace Thelia\Tests\Integration\Domain\Tagging;

use Thelia\Domain\Tagging\Service\TagService;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Tag;
use Thelia\Model\TagElement;
use Thelia\Model\TagElementQuery;
use Thelia\Model\TagQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class TagServiceTest extends IntegrationTestCase
{
    private FixtureFactory $factory;
    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $this->tagService = $this->getService(TagService::class);
    }

    public function testFreeTextCreatesTheTag(): void
    {
        $tag = $this->tagService->findOrCreate('VIP');

        self::assertNotNull($tag->getId());
        self::assertSame('VIP', $tag->getLabel());
    }

    public function testCreateAddsTheTagToTheVocabulary(): void
    {
        $tag = $this->tagService->create('Wholesaler', '#1A2B3C');

        self::assertNotNull($tag->getId());
        self::assertSame('Wholesaler', $tag->getLabel());
        self::assertSame('#1A2B3C', $tag->getColorCode());
    }

    /**
     * Unlike findOrCreate(), which hands back the tag already carrying the
     * label: an administrator who asked to create one is owed the refusal, and
     * a message naming the tag standing in the way. Under utf8mb4_general_ci
     * that tag is spelled differently from what was typed.
     */
    public function testCreateRefusesALabelAnotherTagAlreadyCarries(): void
    {
        $this->tagService->create('Salon 2026');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Salon 2026/');

        $this->tagService->create('salón 2026');
    }

    public function testCreateRefusesALabelOfOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tagService->create('   ');
    }

    public function testTheSameLabelResolvesToTheSameTag(): void
    {
        $first = $this->tagService->findOrCreate('Salon 2026');
        $second = $this->tagService->findOrCreate('Salon 2026');

        self::assertSame($first->getId(), $second->getId());
    }

    /**
     * The de-duplication is the unique index under utf8mb4_general_ci, not a
     * strtolower() in PHP. Asserting it here means a future migration to a
     * case-sensitive collation fails this test instead of silently letting
     * "VIP" and "vip" become two tags.
     */
    public function testALabelDifferingOnlyByCaseResolvesToTheExistingTag(): void
    {
        $original = $this->tagService->findOrCreate('VIP');
        $sameInLowerCase = $this->tagService->findOrCreate('vip');

        self::assertSame($original->getId(), $sameInLowerCase->getId());
        self::assertSame('VIP', $sameInLowerCase->getLabel(), 'The spelling first written is the one kept.');
        self::assertCount(1, TagQuery::create()->filterByLabel('VIP')->find());
    }

    /**
     * Same guard for accents: general_ci folds the Latin range, so "Salón" is
     * "Salon". A collation change would surface right here.
     */
    public function testALabelDifferingOnlyByAccentsResolvesToTheExistingTag(): void
    {
        $original = $this->tagService->findOrCreate('Salon');
        $accented = $this->tagService->findOrCreate('Salón');

        self::assertSame($original->getId(), $accented->getId());
    }

    /**
     * What the collation does NOT fold, and Tag::preSave does.
     */
    public function testInternalWhitespaceIsCollapsed(): void
    {
        $spaced = $this->tagService->findOrCreate("VIP \t club");

        self::assertSame('VIP club', $spaced->getLabel());
        self::assertSame($spaced->getId(), $this->tagService->findOrCreate('VIP club')->getId());
    }

    public function testAnEmptyLabelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tagService->findOrCreate('   ');
    }

    public function testRenameChangesTheLabelAndTheColour(): void
    {
        $tag = $this->tagService->findOrCreate('Prospect', '#111111');

        $this->tagService->rename($tag, '  Qualified   prospect ', '#222222');

        $reloaded = TagQuery::create()->findPk($tag->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Qualified prospect', $reloaded->getLabel(), 'The label is normalized on the way in.');
        self::assertSame('#222222', $reloaded->getColorCode());
    }

    public function testRenamingATagOntoItsOwnLabelIsAllowed(): void
    {
        $tag = $this->tagService->findOrCreate('VIP', '#111111');

        $this->tagService->rename($tag, 'VIP', '#333333');

        self::assertSame('#333333', TagQuery::create()->findPk($tag->getId())?->getColorCode());
    }

    /**
     * The refusal has to name the tag standing in the way. Under
     * utf8mb4_general_ci the collision happens on a spelling that does not look
     * like the target, so "this label already exists" would leave the
     * administrator with no way to find the culprit.
     */
    public function testRenamingOntoAnotherTagLabelIsRefusedAndNamesIt(): void
    {
        $target = $this->tagService->findOrCreate('Salon');
        $renamed = $this->tagService->findOrCreate('Prospect');

        try {
            $this->tagService->rename($renamed, 'Salón');
            self::fail('Renaming onto an existing label must be refused.');
        } catch (\InvalidArgumentException $refusal) {
            self::assertStringContainsString('Salon', $refusal->getMessage(), 'The message names the tag in the way.');
        }

        self::assertSame('Prospect', TagQuery::create()->findPk($renamed->getId())?->getLabel());
        self::assertSame('Salon', TagQuery::create()->findPk($target->getId())?->getLabel());
    }

    public function testRenamingToAnEmptyLabelIsRefused(): void
    {
        $tag = $this->tagService->findOrCreate('VIP');

        $this->expectException(\InvalidArgumentException::class);

        $this->tagService->rename($tag, "  \t ");
    }

    public function testAttachIsIdempotent(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');

        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        self::assertSame(1, $this->countAttachments($customer));
    }

    public function testDetachRemovesOnlyThatAttachment(): void
    {
        $customer = $this->createCustomer();
        $keptTag = $this->tagService->findOrCreate('VIP');
        $removedTag = $this->tagService->findOrCreate('Bad payer');

        $this->tagService->attach($keptTag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->tagService->attach($removedTag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->tagService->detach($removedTag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        self::assertSame([$keptTag->getId()], $this->attachedTagIds($customer));
    }

    public function testFindTagsForReturnsOnlyTheTagsOfThatElementInLabelOrder(): void
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $this->tagService->setLabelsFor(TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId(), ['VIP', 'Grossiste']);
        $this->tagService->setLabelsFor(TagElement::ELEMENT_KEY_CUSTOMER, $otherCustomer->getId(), ['Prospect']);

        $labels = array_map(
            static fn (Tag $tag): string => $tag->getLabel(),
            $this->tagService->findTagsFor(TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId()),
        );

        self::assertSame(['Grossiste', 'VIP'], $labels);
    }

    public function testFindTagsForReturnsNothingForAnElementCarryingNone(): void
    {
        $customer = $this->createCustomer();
        $this->tagService->findOrCreate('VIP');

        self::assertSame([], $this->tagService->findTagsFor(TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId()));
    }

    /**
     * The element key is part of the identity of an attachment, not decoration:
     * a tag put on order 12 must not surface on customer 12.
     */
    public function testFindTagsForSeparatesTwoElementKeysSharingAnIdentifier(): void
    {
        $customer = $this->createCustomer();
        $orderTag = $this->tagService->findOrCreate('Litige');
        $this->tagService->attach($orderTag, 'order', $customer->getId());

        self::assertSame([], $this->tagService->findTagsFor(TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId()));
        self::assertCount(1, $this->tagService->findTagsFor('order', $customer->getId()));
    }

    public function testFindTagsForManyGroupsTheTagsByElement(): void
    {
        $first = $this->createCustomer();
        $second = $this->createCustomer();
        $untagged = $this->createCustomer();

        $this->tagService->setLabelsFor(TagElement::ELEMENT_KEY_CUSTOMER, $first->getId(), ['VIP', 'Grossiste']);
        $this->tagService->setLabelsFor(TagElement::ELEMENT_KEY_CUSTOMER, $second->getId(), ['Prospect']);

        $byCustomer = $this->tagService->findTagsForMany(
            TagElement::ELEMENT_KEY_CUSTOMER,
            [(int) $first->getId(), (int) $second->getId(), (int) $untagged->getId()],
        );

        self::assertSame(
            ['Grossiste', 'VIP'],
            array_map(static fn (Tag $tag): string => $tag->getLabel(), $byCustomer[(int) $first->getId()]),
        );
        self::assertSame(
            ['Prospect'],
            array_map(static fn (Tag $tag): string => $tag->getLabel(), $byCustomer[(int) $second->getId()]),
        );
        self::assertArrayNotHasKey((int) $untagged->getId(), $byCustomer, 'An element carrying nothing is absent, not empty.');
    }

    public function testFindTagsForManyIgnoresAnotherElementKey(): void
    {
        $customer = $this->createCustomer();
        $orderTag = $this->tagService->findOrCreate('Litige');
        $this->tagService->attach($orderTag, 'order', $customer->getId());

        self::assertSame(
            [],
            $this->tagService->findTagsForMany(TagElement::ELEMENT_KEY_CUSTOMER, [(int) $customer->getId()]),
        );
    }

    public function testFindTagsForManyReturnsNothingForAnEmptyList(): void
    {
        self::assertSame([], $this->tagService->findTagsForMany(TagElement::ELEMENT_KEY_CUSTOMER, []));
    }

    public function testSetLabelsForReplacesTheWholeSet(): void
    {
        $customer = $this->createCustomer();
        $obsoleteTag = $this->tagService->findOrCreate('Prospect');
        $this->tagService->attach($obsoleteTag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $tags = $this->tagService->setLabelsFor(
            TagElement::ELEMENT_KEY_CUSTOMER,
            $customer->getId(),
            ['VIP', 'Salon 2026'],
        );

        self::assertCount(2, $tags);
        self::assertNotContains($obsoleteTag->getId(), $this->attachedTagIds($customer));
        self::assertCount(2, $this->attachedTagIds($customer));
    }

    public function testSetLabelsForFoldsTwoSpellingsOfTheSameTag(): void
    {
        $customer = $this->createCustomer();

        $tags = $this->tagService->setLabelsFor(
            TagElement::ELEMENT_KEY_CUSTOMER,
            $customer->getId(),
            ['VIP', 'vip', ' VIP '],
        );

        self::assertCount(1, $tags);
        self::assertSame(1, $this->countAttachments($customer));
    }

    public function testMergeMovesTheAttachmentsAndDropsTheAbsorbedTag(): void
    {
        $onlyAbsorbed = $this->createCustomer();
        $carryingBoth = $this->createCustomer();

        $survivingTag = $this->tagService->findOrCreate('Salon 2026');
        $absorbedTag = $this->tagService->findOrCreate('salon2026');
        self::assertNotSame($survivingTag->getId(), $absorbedTag->getId(), 'These two labels must be distinct tags.');

        $this->tagService->attach($absorbedTag, TagElement::ELEMENT_KEY_CUSTOMER, $onlyAbsorbed->getId());
        $this->tagService->attach($absorbedTag, TagElement::ELEMENT_KEY_CUSTOMER, $carryingBoth->getId());
        $this->tagService->attach($survivingTag, TagElement::ELEMENT_KEY_CUSTOMER, $carryingBoth->getId());

        $this->tagService->merge($absorbedTag, $survivingTag);

        self::assertNull(TagQuery::create()->findPk($absorbedTag->getId()), 'The absorbed tag is gone.');
        self::assertSame([$survivingTag->getId()], $this->attachedTagIds($onlyAbsorbed));
        // The customer already carrying both keeps one attachment, not two: the
        // unique triplet would have refused a second one anyway.
        self::assertSame(1, $this->countAttachments($carryingBoth));
    }

    /**
     * The number the confirmation screen must show. A customer carrying both is
     * one customer, so the answer is smaller than the sum of the two counts.
     */
    public function testCountCustomersCarryingEitherDoesNotDoubleCountTheOverlap(): void
    {
        $onlyFirst = $this->createCustomer();
        $onlySecond = $this->createCustomer();
        $both = $this->createCustomer();

        $first = $this->tagService->findOrCreate('First');
        $second = $this->tagService->findOrCreate('Second');

        $this->tagService->attach($first, TagElement::ELEMENT_KEY_CUSTOMER, $onlyFirst->getId());
        $this->tagService->attach($second, TagElement::ELEMENT_KEY_CUSTOMER, $onlySecond->getId());
        $this->tagService->attach($first, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $this->tagService->attach($second, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());

        $counts = $this->tagService->countCustomersByTag();
        self::assertSame(2, $counts[$first->getId()]);
        self::assertSame(2, $counts[$second->getId()]);

        self::assertSame(3, $this->tagService->countCustomersCarryingEither($first, $second), 'Three customers, not four.');
    }

    public function testTheAnnouncedCountIsWhatTheMergeActuallyProduces(): void
    {
        $onlyFirst = $this->createCustomer();
        $both = $this->createCustomer();

        $absorbed = $this->tagService->findOrCreate('Absorbed');
        $surviving = $this->tagService->findOrCreate('Surviving');

        $this->tagService->attach($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $onlyFirst->getId());
        $this->tagService->attach($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $this->tagService->attach($surviving, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());

        $announced = $this->tagService->countCustomersCarryingEither($absorbed, $surviving);

        $this->tagService->merge($absorbed, $surviving);

        self::assertSame(
            $announced,
            $this->tagService->countCustomersByTag()[$surviving->getId()],
            'What the confirmation announces must be what the merge leaves behind.',
        );
    }

    public function testCountCustomersCarryingEitherIgnoresOrphanedAttachments(): void
    {
        $customer = $this->createCustomer();
        $first = $this->tagService->findOrCreate('First');
        $second = $this->tagService->findOrCreate('Second');
        $this->tagService->attach($first, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->tagService->attach($second, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->deleteCustomerInBulk($customer);

        self::assertSame(0, $this->tagService->countCustomersCarryingEither($first, $second));
    }

    public function testMergingATagIntoItselfIsRefused(): void
    {
        $tag = $this->tagService->findOrCreate('VIP');

        $this->expectException(\InvalidArgumentException::class);

        $this->tagService->merge($tag, $tag);
    }

    public function testCountCustomersByTagCountsInOneQueryAndReportsEmptyTags(): void
    {
        $firstCustomer = $this->createCustomer();
        $secondCustomer = $this->createCustomer();

        $carriedTag = $this->tagService->findOrCreate('VIP');
        $emptyTag = $this->tagService->findOrCreate('Never used');

        $this->tagService->attach($carriedTag, TagElement::ELEMENT_KEY_CUSTOMER, $firstCustomer->getId());
        $this->tagService->attach($carriedTag, TagElement::ELEMENT_KEY_CUSTOMER, $secondCustomer->getId());

        $counts = $this->tagService->countCustomersByTag();

        self::assertSame(2, $counts[$carriedTag->getId()]);
        self::assertSame(0, $counts[$emptyTag->getId()], 'A tag nobody carries is reported at zero, not missing.');
    }

    public function testCountIgnoresAttachmentsLeftBehindByABulkDelete(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->deleteCustomerInBulk($customer);

        self::assertSame(1, $this->countAttachments($customer), 'A bulk delete bypasses postDelete(), so the row is still there.');
        self::assertSame(0, $this->tagService->countCustomersByTag()[$tag->getId()]);
    }

    public function testPruneReportsWithoutDeletingUnlessForced(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->deleteCustomerInBulk($customer);

        self::assertSame(1, $this->tagService->pruneOrphanedCustomerLinks());
        self::assertSame(1, $this->countAttachments($customer), 'A dry run deletes nothing.');

        self::assertSame(1, $this->tagService->pruneOrphanedCustomerLinks(false));
        self::assertSame(0, $this->countAttachments($customer));
    }

    public function testPruneLeavesTheAttachmentsOfLivingCustomersAlone(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        self::assertSame(0, $this->tagService->pruneOrphanedCustomerLinks(false));
        self::assertSame(1, $this->countAttachments($customer));
    }

    public function testDeletingACustomerThroughTheObjectClearsItsAttachments(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $customerId = $customer->getId();
        $customer->delete();

        self::assertSame(
            0,
            TagElementQuery::create()
                ->filterByElementKey(TagElement::ELEMENT_KEY_CUSTOMER)
                ->filterByElementId($customerId)
                ->count(),
            'Customer::postDelete() must clear the attachments a foreign key cannot.',
        );
        self::assertNotNull(TagQuery::create()->findPk($tag->getId()), 'Deleting a customer must not delete the tag itself.');
    }

    public function testDeletingATagCascadesOntoItsAttachments(): void
    {
        $customer = $this->createCustomer();
        $tag = $this->tagService->findOrCreate('VIP');
        $this->tagService->attach($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $tag->delete();

        self::assertSame(0, $this->countAttachments($customer), 'The foreign key on tag_id cascades.');
    }

    private function createCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * A delete that never loads the object, so Customer::postDelete() is not
     * called — the case the prune command exists for.
     */
    private function deleteCustomerInBulk(Customer $customer): void
    {
        CustomerQuery::create()->filterById($customer->getId())->delete();
    }

    private function countAttachments(Customer $customer): int
    {
        return TagElementQuery::create()
            ->filterByElementKey(TagElement::ELEMENT_KEY_CUSTOMER)
            ->filterByElementId($customer->getId())
            ->count();
    }

    /**
     * @return list<int>
     */
    private function attachedTagIds(Customer $customer): array
    {
        $ids = [];

        foreach (TagElementQuery::create()
            ->filterByElementKey(TagElement::ELEMENT_KEY_CUSTOMER)
            ->filterByElementId($customer->getId())
            ->find() as $link) {
            $ids[] = $link->getTagId();
        }

        sort($ids);

        return $ids;
    }
}
