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

namespace Thelia\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Thelia\Messenger\Handler\UndecodableJobHandler;
use Thelia\Messenger\Message\UndecodableJob;

final class UndecodableJobHandlerTest extends TestCase
{
    /**
     * Straight to the failure transport, with no retry: running it again cannot read it.
     */
    public function testAnUnreadableJobFailsForGoodAndSaysWhatItWas(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('RemovedModule\\Message\\SyncStock');

        (new UndecodableJobHandler())(new UndecodableJob('RemovedModule\\Message\\SyncStock', 'module turned off'));
    }
}
