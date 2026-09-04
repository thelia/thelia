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

namespace Thelia\Domain\OrderReturn\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;

/**
 * Default return reference generator: a RET-prefixed, zero-padded number drawn
 * from a gapless transactional sequence, so two concurrent requests never share
 * a reference and the series has no hole.
 */
final readonly class SequenceOrderReturnRefGenerator
{
    public const SEQUENCE_NAME = 'order_return_ref';

    public function __construct(
        private GaplessSequenceGenerator $sequenceGenerator,
    ) {
    }

    public function generate(ConnectionInterface $connection): string
    {
        return self::format($this->sequenceGenerator->next(self::SEQUENCE_NAME, $connection));
    }

    public static function format(int $number): string
    {
        return \sprintf('RET%s', str_pad((string) $number, 12, '0', \STR_PAD_LEFT));
    }
}
