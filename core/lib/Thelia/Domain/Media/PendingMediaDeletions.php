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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The media of the items deleted in a transaction, kept until the deletion that
 * opened it is committed.
 *
 * A deletion runs inside another one: deleting a category deletes its products,
 * each through its own transaction nested in the category one. Propel only commits
 * the outermost transaction, so a product deletion that "commits" may still be
 * rolled back with its category. Each deletion opens a level here alongside its
 * transaction: committed, a nested level hands its media to the level around it,
 * and only the outermost one deletes the files; rolled back, a level forgets them.
 *
 *     $con->beginTransaction();
 *     PendingMediaDeletions::begin();
 *     try {
 *         PendingMediaDeletions::add(DeletedItemMedia::of($product));
 *         $product->delete($con);
 *         $con->commit();
 *     } catch (\Throwable $e) {
 *         $con->rollBack();
 *         PendingMediaDeletions::rollBack();
 *         throw $e;
 *     }
 *     PendingMediaDeletions::commit($dispatcher);
 */
final class PendingMediaDeletions
{
    /** @var list<list<DeletedItemMedia>> the media of each open deletion, the innermost last */
    private static array $levels = [];

    public static function begin(): void
    {
        self::$levels[] = [];
    }

    public static function add(DeletedItemMedia $media): void
    {
        $level = array_key_last(self::$levels) ?? throw new \LogicException('No media deletion is open: call begin() first.');

        self::$levels[$level][] = $media;
    }

    /**
     * Closes the innermost level once its deletion is committed. The outermost level
     * deletes the files with the dispatcher given, none when it is null.
     */
    public static function commit(?EventDispatcherInterface $dispatcher): void
    {
        $committed = array_pop(self::$levels) ?? throw new \LogicException('No media deletion is open.');

        $enclosing = array_key_last(self::$levels);

        if (null !== $enclosing) {
            array_push(self::$levels[$enclosing], ...$committed);

            return;
        }

        if (null === $dispatcher) {
            return;
        }

        foreach ($committed as $media) {
            $media->delete($dispatcher);
        }
    }

    /**
     * Closes the innermost level, its deletion rolled back: its files are kept.
     */
    public static function rollBack(): void
    {
        array_pop(self::$levels);
    }
}
