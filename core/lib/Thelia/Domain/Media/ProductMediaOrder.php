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

namespace Thelia\Domain\Media;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductVideo;
use Thelia\Model\ProductVideoQuery;

/**
 * The one sequence of positions the images and the videos of a product share.
 *
 * The product sheet shows both in a single ordered list, so a position has to
 * mean the same thing in the two tables: a new medium goes after every image and
 * every video the product already has, a move or a reorder rewrites the whole
 * list, and a deletion closes the gap it leaves, whichever table each entry
 * lives in. Two media left on the same position by an older write are told
 * apart the way the sheet shows them: the image first.
 */
final readonly class ProductMediaOrder
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    public function nextPosition(int $productId): int
    {
        $lastImage = ProductImageQuery::create()
            ->filterByProductId($productId)
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $lastVideo = ProductVideoQuery::create()
            ->filterByProductId($productId)
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        return max((int) $lastImage?->getPosition(), (int) $lastVideo?->getPosition()) + 1;
    }

    /**
     * Writes the positions of every image and every video of the product, in the
     * order given: the first entry takes position 1.
     *
     * The list has to name each medium of the product exactly once. An entry from
     * another product, a duplicate or a medium left out is refused before anything
     * is written, so the sheet never ends up half reordered. The media are read
     * and locked inside the transaction that writes them: a medium deleted by
     * someone else in the meantime is refused rather than silently written.
     *
     * @param list<array{type: string, id: int}> $order
     *
     * @throws \InvalidArgumentException when the list does not match the media of the product
     */
    public function reorder(int $productId, array $order): void
    {
        $this->transaction(function (ConnectionInterface $connection) use ($productId, $order): void {
            $media = $this->media($productId, $connection);

            if (\count($order) !== \count($media)) {
                throw new \InvalidArgumentException(\sprintf('The order names %d media, the product has %d.', \count($order), \count($media)));
            }

            $byKey = [];
            foreach ($media as $medium) {
                $byKey[self::keyOf($medium)] = $medium;
            }

            $ordered = [];
            foreach ($order as $entry) {
                $type = (string) ($entry['type'] ?? '');
                $id = (int) ($entry['id'] ?? 0);
                $key = $type.':'.$id;

                if (!isset($byKey[$key])) {
                    throw new \InvalidArgumentException(\sprintf('No %s #%d on product #%d, or named twice.', '' === $type ? 'medium' : $type, $id, $productId));
                }

                $ordered[] = $byKey[$key];
                unset($byKey[$key]);
            }

            $this->write($ordered, $connection);
        });
    }

    /**
     * Moves one medium to the given rank among the media of its product, the
     * others keeping their order: what a merchant means by "put this one third".
     * A rank beyond the last medium puts it last.
     */
    public function moveTo(ProductImage|ProductVideo $medium, int $position): void
    {
        $this->transaction(function (ConnectionInterface $connection) use ($medium, $position): void {
            $others = array_values(array_filter(
                $this->media((int) $medium->getProductId(), $connection),
                static fn (ProductImage|ProductVideo $candidate): bool => self::keyOf($candidate) !== self::keyOf($medium),
            ));

            $index = max(0, min($position - 1, \count($others)));
            array_splice($others, $index, 0, [$medium]);

            $this->write($others, $connection);
        });
    }

    /**
     * Closes the gap a deleted medium leaves: the remaining media are renumbered
     * from 1 in the order they already have.
     */
    public function compact(int $productId): void
    {
        $this->transaction(function (ConnectionInterface $connection) use ($productId): void {
            $this->write($this->media($productId, $connection), $connection);
        });
    }

    /**
     * Every medium of the product, in the order the sheet shows them: by
     * position, an image before a video on a tie. The rows are locked for the
     * transaction the connection is in.
     *
     * @return list<ProductImage|ProductVideo>
     */
    private function media(int $productId, ConnectionInterface $connection): array
    {
        $images = ProductImageQuery::create()
            ->filterByProductId($productId)
            ->orderByPosition()
            ->orderById()
            ->lockForUpdate()
            ->find($connection)
            ->getData();

        $videos = ProductVideoQuery::create()
            ->filterByProductId($productId)
            ->orderByPosition()
            ->orderById()
            ->lockForUpdate()
            ->find($connection)
            ->getData();

        // usort is stable: on a tie the images, listed first, stay first.
        $media = array_merge($images, $videos);
        usort($media, static fn (ProductImage|ProductVideo $a, ProductImage|ProductVideo $b): int => (int) $a->getPosition() <=> (int) $b->getPosition());

        return $media;
    }

    /**
     * @param list<ProductImage|ProductVideo> $ordered
     */
    private function write(array $ordered, ConnectionInterface $connection): void
    {
        foreach ($ordered as $index => $medium) {
            $position = $index + 1;
            if ((int) $medium->getPosition() === $position) {
                continue;
            }
            $medium->setPosition($position);
            $medium->save($connection);
        }
    }

    /**
     * @param callable(ConnectionInterface): void $work
     */
    private function transaction(callable $work): void
    {
        $connection = Propel::getWriteConnection(ProductImageTableMap::DATABASE_NAME);
        $connection->transaction(static function () use ($work, $connection): void {
            $work($connection);
        });
    }

    private static function keyOf(ProductImage|ProductVideo $medium): string
    {
        return ($medium instanceof ProductVideo ? self::TYPE_VIDEO : self::TYPE_IMAGE).':'.$medium->getId();
    }
}
