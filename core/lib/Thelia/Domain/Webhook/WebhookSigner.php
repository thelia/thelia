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

namespace Thelia\Domain\Webhook;

final readonly class WebhookSigner
{
    public const string HEADER_ID = 'X-Thelia-Webhook-Id';
    public const string HEADER_TIMESTAMP = 'X-Thelia-Webhook-Timestamp';
    public const string HEADER_SIGNATURE = 'X-Thelia-Webhook-Signature';

    public function sign(string $body, string $secret, int $timestamp): string
    {
        return 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verify(string $body, string $secret, int $timestamp, string $signature, int $now, int $tolerance = 300): bool
    {
        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals($this->sign($body, $secret, $timestamp), $signature);
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $body, string $secret, string $deliveryId, int $timestamp): array
    {
        return [
            'Content-Type' => 'application/json',
            self::HEADER_ID => $deliveryId,
            self::HEADER_TIMESTAMP => (string) $timestamp,
            self::HEADER_SIGNATURE => $this->sign($body, $secret, $timestamp),
        ];
    }
}
