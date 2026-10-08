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
namespace Thelia\Core\Archiver;

/**
 * An archiver that keeps its archive open between two calls: a worker lets go of it
 * once the archive is whole, or drops what it holds when the export fails.
 */
interface ClosableArchiverInterface
{
    /**
     * Writes what the archive holds, if it is not written yet, and lets go of it.
     */
    public function close(): bool;

    /**
     * Lets go of the archive without writing what it holds yet: the caller removes the
     * file.
     */
    public function discard(): void;
}
