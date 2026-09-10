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

namespace Thelia\Domain\Tagging\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Model\Map\TagTableMap;
use Thelia\Model\Tag;
use Thelia\Model\TagElement;
use Thelia\Model\TagElementQuery;
use Thelia\Model\TagQuery;

/**
 * Creates tags and attaches them to shop objects.
 *
 * Uniqueness of a label is enforced by the unique index, not by a look-up
 * followed by an insert: two administrators typing "VIP" at the same moment
 * both pass a look-up and only one passes the index.
 */
final readonly class TagService
{
    /**
     * Returns the tag carrying this label, creating it if no tag does.
     *
     * The look-up is a fast path, not the guarantee: the guarantee is the unique
     * index, and losing the race is a normal outcome that resolves to the tag the
     * winner created.
     */
    public function findOrCreate(string $label, ?string $colorCode = null, ?ConnectionInterface $connection = null): Tag
    {
        $normalizedLabel = Tag::normalizeLabel($label);

        if ($normalizedLabel === '') {
            throw new \InvalidArgumentException('A tag label cannot be empty.');
        }

        $existingTag = TagQuery::create()->findOneByLabel($normalizedLabel, $connection);

        if ($existingTag instanceof Tag) {
            return $existingTag;
        }

        try {
            $tag = (new Tag())
                ->setLabel($normalizedLabel)
                ->setColorCode($colorCode);
            $tag->save($connection);

            return $tag;
        } catch (PropelException $exception) {
            // The other writer got there first. Its label compares equal to ours
            // under the index collation, which is not the same as being the same
            // string: "Salón" resolves to the "Salon" already on file, so the
            // look-up has to go through the index too, not through our spelling.
            $winningTag = TagQuery::create()->findOneByLabel($normalizedLabel, $connection);

            if (!$winningTag instanceof Tag) {
                throw $exception;
            }

            return $winningTag;
        }
    }

    /**
     * Renames a tag, and recolours it.
     *
     * The collision is looked up rather than left to the unique index, for one
     * reason: the index answers "taken", not "taken by which one". Under
     * utf8mb4_general_ci a rename collides on a spelling that does not look like
     * the target — renaming "Salón" to "Salon" is a duplicate, and so is any
     * change of case — so the caller has to be able to name the tag standing in
     * the way, or the administrator cannot understand the refusal.
     *
     * @throws \InvalidArgumentException when the label is empty or already taken
     */
    public function rename(Tag $tag, string $label, ?string $colorCode = null, ?ConnectionInterface $connection = null): Tag
    {
        $normalizedLabel = Tag::normalizeLabel($label);

        if ($normalizedLabel === '') {
            throw new \InvalidArgumentException('A tag label cannot be empty.');
        }

        $conflicting = TagQuery::create()->findOneByLabel($normalizedLabel, $connection);

        if ($conflicting instanceof Tag && $conflicting->getId() !== $tag->getId()) {
            throw new \InvalidArgumentException(\sprintf('The label "%s" is already carried by the tag "%s". Merge them instead of renaming.', $normalizedLabel, (string) $conflicting->getLabel()));
        }

        $tag->setLabel($normalizedLabel)->setColorCode($colorCode)->save($connection);

        return $tag;
    }

    /**
     * Attaches a tag to an object, doing nothing when it is already attached.
     *
     * The connection is a parameter rather than something this method fetches for
     * itself: setLabelsFor() and merge() call it from inside their own transaction,
     * and a method that reached for Propel::getConnection() on its own would quietly
     * step outside that transaction the day this datasource is split for reads.
     */
    public function attach(Tag $tag, string $elementKey, int $elementId, ?ConnectionInterface $connection = null): void
    {
        $isAlreadyAttached = TagElementQuery::create()
            ->filterByTagId($tag->getId())
            ->filterByElementKey($elementKey)
            ->filterByElementId($elementId)
            ->exists($connection);

        if ($isAlreadyAttached) {
            return;
        }

        try {
            (new TagElement())
                ->setTagId($tag->getId())
                ->setElementKey($elementKey)
                ->setElementId($elementId)
                ->save($connection);
        } catch (PropelException) {
            // Concurrent attach of the same triplet: the unique index refused the
            // duplicate, which is the outcome we wanted anyway.
        }
    }

    public function detach(Tag $tag, string $elementKey, int $elementId, ?ConnectionInterface $connection = null): void
    {
        TagElementQuery::create()
            ->filterByTagId($tag->getId())
            ->filterByElementKey($elementKey)
            ->filterByElementId($elementId)
            ->delete($connection);
    }

    /**
     * Makes the tags of an object exactly the given labels, creating those that
     * do not exist yet and detaching those no longer listed.
     *
     * @param list<string> $labels
     *
     * @return list<Tag> the tags the object carries afterwards
     */
    public function setLabelsFor(string $elementKey, int $elementId, array $labels): array
    {
        $connection = Propel::getConnection(TagTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $tags = [];

            foreach ($labels as $label) {
                if (Tag::normalizeLabel($label) === '') {
                    continue;
                }

                $tag = $this->findOrCreate($label, null, $connection);
                // findOrCreate resolves distinct spellings to one tag, so the same
                // tag can come back twice from a list holding "VIP" and "vip".
                $tags[$tag->getId()] = $tag;
            }

            $keptTagIds = array_keys($tags);

            $obsoleteLinks = TagElementQuery::create()
                ->filterByElementKey($elementKey)
                ->filterByElementId($elementId);

            if ($keptTagIds !== []) {
                $obsoleteLinks->filterByTagId($keptTagIds, Criteria::NOT_IN);
            }

            $obsoleteLinks->delete($connection);

            foreach ($tags as $tag) {
                $this->attach($tag, $elementKey, $elementId, $connection);
            }

            $connection->commit();

            return array_values($tags);
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * Moves every attachment of the absorbed tag onto the surviving one, then
     * deletes the absorbed tag.
     *
     * Irreversible, and the caller owes the confirmation.
     */
    public function merge(Tag $absorbedTag, Tag $survivingTag): void
    {
        if ($absorbedTag->getId() === $survivingTag->getId()) {
            throw new \InvalidArgumentException('A tag cannot be merged into itself.');
        }

        $connection = Propel::getConnection(TagTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $absorbedLinks = TagElementQuery::create()
                ->filterByTagId($absorbedTag->getId())
                ->find($connection);

            foreach ($absorbedLinks as $absorbedLink) {
                // Re-pointing the row would break the unique triplet whenever the
                // object already carries the surviving tag, so attach-then-drop
                // rather than update: attach() is a no-op on the objects that
                // carry both.
                $this->attach($survivingTag, $absorbedLink->getElementKey(), $absorbedLink->getElementId(), $connection);
            }

            // The foreign key on tag_id cascades, so deleting the tag takes its
            // remaining links with it.
            $absorbedTag->delete($connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * How many live customers would carry the surviving tag after a merge.
     *
     * Not the sum of the two counts: a customer carrying both is one customer,
     * and the merge leaves it with one attachment. The screen that asks for the
     * confirmation has to show this number and not the sum, or it announces a
     * result that will not happen.
     */
    public function countCustomersCarryingEither(Tag $first, Tag $second, ?ConnectionInterface $connection = null): int
    {
        $connection ??= Propel::getConnection(TagTableMap::DATABASE_NAME);

        $statement = $connection->prepare(
            'SELECT COUNT(DISTINCT c.id)
               FROM tag_element te
               INNER JOIN customer c ON c.id = te.element_id
              WHERE te.element_key = :element_key
                AND te.tag_id IN (:first_tag, :second_tag)'
        );
        $statement->execute([
            ':element_key' => TagElement::ELEMENT_KEY_CUSTOMER,
            ':first_tag' => $first->getId(),
            ':second_tag' => $second->getId(),
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Number of live customers carrying each tag, in one grouped query.
     *
     * Joined to `customer` on purpose: a bulk delete that bypassed
     * Customer::postDelete() leaves attachments behind, and counting the
     * attachments instead of the customers would report them.
     *
     * @return array<int, int> tag id => customer count, tags with none included
     */
    public function countCustomersByTag(?ConnectionInterface $connection = null): array
    {
        $connection ??= Propel::getConnection(TagTableMap::DATABASE_NAME);

        $statement = $connection->prepare(
            'SELECT t.id AS tag_id, COUNT(c.id) AS customer_count
               FROM tag t
               LEFT JOIN tag_element te ON te.tag_id = t.id AND te.element_key = :element_key
               LEFT JOIN customer c ON c.id = te.element_id
              GROUP BY t.id'
        );
        $statement->execute([':element_key' => TagElement::ELEMENT_KEY_CUSTOMER]);

        $counts = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(int) $row['tag_id']] = (int) $row['customer_count'];
        }

        return $counts;
    }

    /**
     * Deletes the attachments pointing at a customer that no longer exists.
     *
     * Customer::postDelete() covers every deletion that goes through the object.
     * A bulk ModelCriteria::delete(), a hand-written DELETE or a module doing its
     * own clean-up never reaches it, so this stays necessary.
     *
     * @return int the number of orphaned attachments found, deleted unless $dryRun
     */
    public function pruneOrphanedCustomerLinks(bool $dryRun = true, ?ConnectionInterface $connection = null): int
    {
        $connection ??= Propel::getConnection(TagTableMap::DATABASE_NAME);

        $countStatement = $connection->prepare(
            'SELECT COUNT(*) FROM tag_element te
               LEFT JOIN customer c ON c.id = te.element_id
              WHERE te.element_key = :element_key AND c.id IS NULL'
        );
        $countStatement->execute([':element_key' => TagElement::ELEMENT_KEY_CUSTOMER]);
        $orphanCount = (int) $countStatement->fetchColumn();

        if ($dryRun || $orphanCount === 0) {
            return $orphanCount;
        }

        $deleteStatement = $connection->prepare(
            'DELETE te FROM tag_element te
               LEFT JOIN customer c ON c.id = te.element_id
              WHERE te.element_key = :element_key AND c.id IS NULL'
        );
        $deleteStatement->execute([':element_key' => TagElement::ELEMENT_KEY_CUSTOMER]);

        return $orphanCount;
    }
}
