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

use Thelia\Domain\Legal\Service\NullVatNumberVerifier;
use Thelia\Domain\Legal\Service\VatNumberVerifierInterface;

/*
 * Single substitution point for VAT number verification: alias
 * VatNumberVerifierInterface to your own verifier from a module
 * configureServices() to plug an authority in.
 *
 * The default alias is declared here rather than with #[AsAlias] on the null
 * implementation, so that an active module can freely declare its own —
 * Symfony refuses two concurrent #[AsAlias] declarations for the same id.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(VatNumberVerifierInterface::class, NullVatNumberVerifier::class);
};
