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

namespace Thelia\Tests\Support\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Counts what reaches the serializer behind a decorator, and builds nothing.
 */
final class InnerSerializerSpy implements SerializerInterface
{
    public int $decoded = 0;

    public int $encoded = 0;

    public function decode(array $encodedEnvelope): Envelope
    {
        ++$this->decoded;

        return new Envelope(new ProbeMessage('decoded'));
    }

    public function encode(Envelope $envelope): array
    {
        ++$this->encoded;

        return ['body' => '{}', 'headers' => []];
    }
}
