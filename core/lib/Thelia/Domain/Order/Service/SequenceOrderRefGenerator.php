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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;

/**
 * Default order reference generator: same ORD-prefixed, zero-padded format as
 * historical references, but numbered from a gapless transactional sequence
 * instead of the table auto-increment.
 */
#[AsAlias(OrderRefGeneratorInterface::class)]
final readonly class SequenceOrderRefGenerator implements OrderRefGeneratorInterface
{
    public const SEQUENCE_NAME = 'order_ref';

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
        return \sprintf('ORD%s', str_pad((string) $number, 12, '0', \STR_PAD_LEFT));
    }
}
