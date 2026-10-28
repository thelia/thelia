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

namespace AiProbeModule;

/**
 * What the probe tools did and how they behave, set by the test that runs them.
 */
final class ProbeToolState
{
    public static int $executeCalls = 0;

    public static int $proposeCalls = 0;

    public static int $applyCalls = 0;

    public static string $readBehavior = 'ok';

    public static string $applyBehavior = 'ok';

    /** @var \Closure(): void|null */
    public static ?\Closure $onExecute = null;

    /** @var \Closure(): void|null */
    public static ?\Closure $onPropose = null;

    /** @var \Closure(): void|null */
    public static ?\Closure $onApply = null;

    /** @var \Thelia\Domain\Ai\Tool\AiApprovedChange|null */
    public static ?object $lastChange = null;

    /** @var \Thelia\Domain\Ai\Tool\AiToolContext|null */
    public static ?object $lastProposeContext = null;

    public static function reset(): void
    {
        self::$executeCalls = self::$proposeCalls = self::$applyCalls = 0;
        self::$readBehavior = self::$applyBehavior = 'ok';
        self::$onExecute = self::$onPropose = self::$onApply = null;
        self::$lastChange = self::$lastProposeContext = null;
    }
}
