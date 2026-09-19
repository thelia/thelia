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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Domain\Checkout\Service\ConsentAcceptanceReaderInterface;
use Thelia\Domain\Checkout\Service\ConsentAnswerChain;
use Thelia\Domain\Checkout\Service\ConsentAnswerStoreInterface;

/*
 * Single substitution point for the consent answers the checkout reads: alias
 * ConsentAcceptanceReaderInterface to your own service from a module
 * configureServices() to have the progression, the payment step and the order
 * validation all read them from somewhere other than the shipped sources.
 *
 * Those shipped sources are two, and ConsentAnswerChain is what puts them in order: the
 * answers stated in the request that places the order come first, and the session a
 * buyer walking the screens of a theme ticked their boxes in answers when there are
 * none. A module aliasing the interface to its own service replaces both.
 *
 * ConsentAnswerStoreInterface is the same chain, asked the fuller question: what was
 * answered, under which wording and when, which is what gets frozen onto the order as
 * proof. A module that substitutes its own source of answers must rebind BOTH aliases:
 * rebinding the reader alone changes what the guard accepts on while the proof written
 * onto the order keeps coming from the shipped chain — the divergence this wiring
 * exists to prevent. The reader stays a separate, one-method interface for the modules
 * that only ever read.
 *
 * The aliases are public so that code holding only the container, such as a module
 * built on BaseModule, can fetch the reader without autowiring.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(ConsentAcceptanceReaderInterface::class, ConsentAnswerChain::class)
        ->public();

    $services->alias(ConsentAnswerStoreInterface::class, ConsentAnswerChain::class)
        ->public();
};
