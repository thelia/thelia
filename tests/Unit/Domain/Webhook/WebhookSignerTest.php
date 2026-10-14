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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Webhook\WebhookSigner;

final class WebhookSignerTest extends TestCase
{
    private const SECRET = 'whsec_test';
    private const BODY = '{"type":"order.created","data":{"id":12}}';

    public function testTheSignatureIsTheHmacOfTheTimestampAndTheRawBody(): void
    {
        $signature = (new WebhookSigner())->sign(self::BODY, self::SECRET, 1_700_000_000);

        self::assertSame('v1='.hash_hmac('sha256', '1700000000.'.self::BODY, self::SECRET), $signature);
    }

    public function testAnAlteredBodyIsRejected(): void
    {
        $signer = new WebhookSigner();
        $signature = $signer->sign(self::BODY, self::SECRET, 1_700_000_000);

        self::assertTrue($signer->verify(self::BODY, self::SECRET, 1_700_000_000, $signature, 1_700_000_010));
        self::assertFalse($signer->verify(self::BODY.' ', self::SECRET, 1_700_000_000, $signature, 1_700_000_010));
    }

    public function testAnotherSecretIsRejected(): void
    {
        $signer = new WebhookSigner();
        $signature = $signer->sign(self::BODY, self::SECRET, 1_700_000_000);

        self::assertFalse($signer->verify(self::BODY, 'other', 1_700_000_000, $signature, 1_700_000_010));
    }

    public function testAReplayedTimestampOutsideTheToleranceIsRejected(): void
    {
        $signer = new WebhookSigner();
        $signature = $signer->sign(self::BODY, self::SECRET, 1_700_000_000);

        self::assertFalse($signer->verify(self::BODY, self::SECRET, 1_700_000_000, $signature, 1_700_000_000 + 301));
        self::assertTrue($signer->verify(self::BODY, self::SECRET, 1_700_000_000, $signature, 1_700_000_000 + 300));
    }

    public function testTheHeadersCarryTheDeliveryIdentifierTheTimestampAndTheSignature(): void
    {
        $headers = (new WebhookSigner())->headers(self::BODY, self::SECRET, '7c1f', 1_700_000_000);

        self::assertSame('7c1f', $headers['X-Thelia-Webhook-Id']);
        self::assertSame('1700000000', $headers['X-Thelia-Webhook-Timestamp']);
        self::assertSame((new WebhookSigner())->sign(self::BODY, self::SECRET, 1_700_000_000), $headers['X-Thelia-Webhook-Signature']);
        self::assertSame('application/json', $headers['Content-Type']);
    }
}
