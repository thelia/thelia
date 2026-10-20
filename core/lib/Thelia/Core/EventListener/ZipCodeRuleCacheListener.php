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

namespace Thelia\Core\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\AreaZipCodeRuleQuery;

/**
 * Keeps {@see AreaZipCodeRuleQuery::ruleSetFor()} answering from the rules of the current
 * request: what it read lives no longer than a request, a console command or, in a worker,
 * one message, so a rule written by another process is seen on the next one.
 */
final readonly class ZipCodeRuleCacheListener implements ResetInterface
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            AreaZipCodeRuleQuery::forgetRuleSets();
        }
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(): void
    {
        AreaZipCodeRuleQuery::forgetRuleSets();
    }

    public function reset(): void
    {
        AreaZipCodeRuleQuery::forgetRuleSets();
    }
}
