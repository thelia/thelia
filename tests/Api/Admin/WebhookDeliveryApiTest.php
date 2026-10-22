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

namespace Thelia\Tests\Api\Admin;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookSubscription;
use Thelia\Test\ApiTestCase;

final class WebhookDeliveryApiTest extends ApiTestCase
{
    private const URI = '/api/admin/webhook-deliveries';
    private const BODY_MARKER = 'customer-private-body-marker';
    private const ANSWER_MARKER = 'receiver-echoed-answer-marker';

    public function testTheLogIsReadOnlyAndShowsTheOutcomeOfEachRequest(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED, 'order.created', 500);

        $response = $this->jsonRequest('GET', self::URI.'/'.$delivery->getId(), token: $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame($subscription->getId(), $data['subscriptionId']);
        self::assertSame('order.created', $data['event']);
        self::assertSame(WebhookStatus::DELIVERY_FAILED, $data['status']);
        self::assertSame(500, $data['responseCode']);
        self::assertSame(1, $data['attempts']);
        self::assertSame($delivery->getDeliveryUuid(), $data['deliveryUuid']);
    }

    public function testNeitherTheSignedBodyNorTheAnswerNorTheSecretIsExposed(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);

        foreach ([self::URI, self::URI.'/'.$delivery->getId()] as $uri) {
            $content = (string) $this->jsonRequest('GET', $uri, token: $token)->getContent();

            self::assertStringNotContainsString(self::BODY_MARKER, $content, $uri);
            self::assertStringNotContainsString(self::ANSWER_MARKER, $content, $uri);
            self::assertStringNotContainsString($subscription->getSecret(), $content, $uri);
            self::assertStringNotContainsString('payload', $content, $uri);
            self::assertStringNotContainsString('responseBody', $content, $uri);
        }
    }

    public function testNothingWritesToTheLog(): void
    {
        $token = $this->authenticateAsAdmin();
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_FAILED);
        $item = self::URI.'/'.$delivery->getId();

        foreach ([['POST', self::URI], ['PATCH', $item], ['PUT', $item], ['DELETE', $item]] as [$method, $uri]) {
            $response = $this->jsonRequest($method, $uri, ['status' => 'succeeded'], $token, 'PATCH' === $method ? 'merge-patch+json' : 'jsonld');

            self::assertSame(405, $response->getStatusCode(), $method.' '.$uri);
        }
    }

    public function testTheLogFiltersOnSubscriptionEventStatusAndDate(): void
    {
        $token = $this->authenticateAsAdmin();
        $first = $this->subscription();
        $second = $this->subscription();
        $this->delivery($first, WebhookStatus::DELIVERY_FAILED, 'order.created');
        $this->delivery($first, WebhookStatus::DELIVERY_SUCCEEDED, 'order.paid');
        $this->delivery($second, WebhookStatus::DELIVERY_FAILED, 'order.created');

        self::assertSame(3, $this->total(self::URI, $token));
        self::assertSame(2, $this->total(self::URI.'?subscriptionId='.$first->getId(), $token));
        self::assertSame(1, $this->total(self::URI.'?subscriptionId='.$second->getId(), $token));
        self::assertSame(2, $this->total(self::URI.'?event=order.created', $token));
        self::assertSame(2, $this->total(self::URI.'?status=failed', $token));
        self::assertSame(1, $this->total(self::URI.'?subscriptionId='.$first->getId().'&status=succeeded', $token));
        self::assertSame(3, $this->total(self::URI.'?createdAt[after]=2000-01-01', $token));
        self::assertSame(0, $this->total(self::URI.'?createdAt[before]=2000-01-01', $token));
    }

    public function testThePageIsCappedBelowTheGlobalCeiling(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        for ($i = 0; $i < 55; ++$i) {
            $this->delivery($subscription, WebhookStatus::DELIVERY_SUCCEEDED);
        }

        $capped = json_decode((string) $this->jsonRequest('GET', self::URI.'?itemsPerPage=100000', token: $token)->getContent(), true);
        $default = json_decode((string) $this->jsonRequest('GET', self::URI, token: $token)->getContent(), true);

        self::assertCount(50, $capped['hydra:member']);
        self::assertSame(55, $capped['hydra:totalItems']);
        self::assertCount(25, $default['hydra:member']);
    }

    public function testAskingForNoPaginationDoesNotLiftTheCap(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();

        for ($i = 0; $i < 55; ++$i) {
            $this->delivery($subscription, WebhookStatus::DELIVERY_SUCCEEDED);
        }

        $all = json_decode((string) $this->jsonRequest('GET', self::URI.'?itemsPerPage=0', token: $token)->getContent(), true);

        self::assertCount(50, $all['hydra:member']);
        self::assertSame(55, $all['hydra:totalItems']);
    }

    public function testTheLogFollowsTheWebhookPermission(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $factory = $this->createFixtureFactory();

        $without = $this->authenticateAsAdmin($factory->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]));
        self::assertSame(403, $this->jsonRequest('GET', self::URI, token: $without)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', self::URI.'/'.$delivery->getId(), token: $without)->getStatusCode());

        $reader = $this->authenticateAsAdmin($factory->restrictedAdmin([AdminResources::WEBHOOK => [AccessManager::VIEW]]));
        self::assertSame(200, $this->jsonRequest('GET', self::URI, token: $reader)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', self::URI.'/'.$delivery->getId(), token: $reader)->getStatusCode());

        self::assertSame(401, $this->jsonRequest('GET', self::URI)->getStatusCode());
        self::assertSame(403, $this->jsonRequest('GET', self::URI, token: $this->authenticateAsCustomer())->getStatusCode());
    }

    private function total(string $uri, string $token): int
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);

        self::assertSame(200, $response->getStatusCode(), $uri.': '.$response->getContent());

        return (int) json_decode((string) $response->getContent(), true)['hydra:totalItems'];
    }

    private function subscription(): WebhookSubscription
    {
        $subscription = (new WebhookSubscription())
            ->setTitle('Existing')
            ->setUrl('https://hooks.example.com/in')
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEvents('["order.created"]')
            ->setPayloadMode(WebhookStatus::PAYLOAD_FULL)
            ->setStatus(WebhookStatus::SUBSCRIPTION_ACTIVE);
        $subscription->save();

        return $subscription;
    }

    private function delivery(WebhookSubscription $subscription, string $status, string $event = 'order.created', ?int $code = null): WebhookDelivery
    {
        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent($event)
            ->setDeliveryUuid(bin2hex(random_bytes(18)))
            ->setPayload('{"id":"x","email":"'.self::BODY_MARKER.'"}')
            ->setResponseBody(self::ANSWER_MARKER)
            ->setResponseCode($code)
            ->setStatus($status)
            ->setAttempts(1);
        $delivery->save();

        return $delivery;
    }
}
