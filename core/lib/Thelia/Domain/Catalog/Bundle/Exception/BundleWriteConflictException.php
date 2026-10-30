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

namespace Thelia\Domain\Catalog\Bundle\Exception;

/**
 * The database broke a lock conflict between this change of a bundle and another writer of the
 * same products: a deadlock (1213), or on MariaDB 11.6 and later a row changed since the
 * transaction took its picture (1020). The write was rolled back whole; made again a moment
 * later it is judged on what the other writer left. The API answers it with a 409.
 */
final class BundleWriteConflictException extends \DomainException
{
    public static function from(\Throwable $conflict): self
    {
        return new self('Another change of the same products was made at the same moment. Please try again.', 0, $conflict);
    }
}
