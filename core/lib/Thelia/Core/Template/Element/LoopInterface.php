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

namespace Thelia\Core\Template\Element;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;

interface LoopInterface
{
    public function init(
        ContainerInterface $container,
        RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher,
        SecurityContext $securityContext,
        TranslatorInterface $translator,
        array $theliaParserLoops,
        $kernelEnvironment,
    ): void;

    public function initializeArgs(array $nameValuePairs): void;

    public function count();

    public function exec(&$pagination);
}
