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

namespace Thelia\Domain\Ai\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiJurisdiction;

/**
 * Raised only when the merchant turned on the optional setting "EU only" (off by default): it then refuses
 * a connection, a provider tool the request asks for, or an external service
 * (AiCostRecorderInterface::authorize()) that processes data outside the European Union.
 */
#[Exclude]
final class AiJurisdictionRefusedException extends \RuntimeException implements AiExceptionInterface
{
    /**
     * @param string $connection the code of the connection, or the external service, refused
     */
    public function __construct(public readonly string $connection, public readonly AiJurisdiction $jurisdiction)
    {
        parent::__construct(\sprintf('"%s" processes data outside the European Union and the shop allows EU providers only.', $connection));
    }
}
