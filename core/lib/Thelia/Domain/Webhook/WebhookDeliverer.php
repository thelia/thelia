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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Thelia\Domain\Webhook\Exception\InvalidWebhookUrlException;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookSubscription;

final readonly class WebhookDeliverer
{
    private const int RESPONSE_BODY_LIMIT = 1000;

    public function __construct(
        #[Autowire(service: 'thelia.webhook.http_client')]
        private HttpClientInterface $client,
        private WebhookSigner $signer,
        private WebhookUrlValidator $urlValidator,
    ) {
    }

    /**
     * Sends one attempt and records its outcome on the delivery.
     */
    public function deliver(WebhookDelivery $delivery, WebhookSubscription $subscription): bool
    {
        $startedAt = hrtime(true);
        $code = null;
        $body = null;

        try {
            $this->urlValidator->validate($subscription->getUrl());

            $response = $this->client->request('POST', $subscription->getUrl(), [
                'headers' => $this->signer->headers($delivery->getPayload(), $subscription->getSecret(), $delivery->getDeliveryUuid(), time()),
                'body' => $delivery->getPayload(),
            ]);
            $code = $response->getStatusCode();
            $body = $this->readLimited($response);
        } catch (InvalidWebhookUrlException $exception) {
            $body = $exception->getMessage();
        } catch (\Throwable $exception) {
            $body = $exception::class;
        }

        $succeeded = null !== $code && $code >= 200 && $code < 300;

        $delivery
            ->setAttempts($delivery->getAttempts() + 1)
            ->setResponseCode($code)
            ->setDurationMs((int) round((hrtime(true) - $startedAt) / 1_000_000))
            ->setResponseBody($this->safeBody($body, $subscription->getSecret()))
            ->setStatus($succeeded ? WebhookStatus::DELIVERY_SUCCEEDED : $delivery->getStatus())
            ->setDeliveredAt($succeeded ? new \DateTimeImmutable() : null)
            ->save();

        return $succeeded;
    }

    private function readLimited(ResponseInterface $response): string
    {
        $body = '';

        foreach ($this->client->stream($response) as $chunk) {
            $body .= $chunk->getContent();

            if (\strlen($body) >= self::RESPONSE_BODY_LIMIT) {
                $response->cancel();

                break;
            }
        }

        return $body;
    }

    private function safeBody(?string $body, string $secret): ?string
    {
        if (null === $body) {
            return null;
        }

        $body = str_replace($secret, '[redacted]', mb_scrub(mb_strcut($body, 0, self::RESPONSE_BODY_LIMIT)));

        return $body;
    }
}
