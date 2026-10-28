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

namespace Thelia\Domain\Ai;

/**
 * What a usage needs from the model. The model assigned to its tier must have every capability the usage
 * declares, or the usage is inactive; a request may only use the capabilities its usage declared.
 */
enum AiCapability: string
{
    /** The answer follows a JSON schema (AiRequest::$outputSchema). */
    case StructuredOutput = 'structured_output';
    /** The model may call the tools the module describes (AiRequest::$tools). */
    case Tools = 'tools';
    /** The model reads images. */
    case Vision = 'vision';
    /** The answer is read as it is produced (AiGatewayInterface::stream()). */
    case Streaming = 'streaming';
}
