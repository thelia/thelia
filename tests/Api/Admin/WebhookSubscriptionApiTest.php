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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\Admin;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\ApiTestCase;

final class WebhookSubscriptionApiTest extends ApiTestCase
{
    private const URI = '/api/admin/webhook-subscriptions';
    private const RECEIVER = 'https://hooks.example.com/in';

    public function testPostCreatesASubscriptionAndHandsTheSecretOver(): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', self::URI, [
            'title' => 'ERP',
            'url' => self::RECEIVER,
            'events' => ['order.created', 'order.paid'],
            'payloadMode' => WebhookStatus::PAYLOAD_IDENTIFIERS,
        ], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('ERP', $data['title']);
        self::assertSame(['order.created', 'order.paid'], $data['events']);
        self::assertSame(WebhookStatus::PAYLOAD_IDENTIFIERS, $data['payloadMode']);
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $data['status']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $data['secret']);
        self::assertSame($data['secret'], WebhookSubscriptionQuery::create()->findPk($data['id'])?->getSecret());
    }

    public function testARowWhoseEventsCannotBeReadDoesNotBreakTheWholeList(): void
    {
        $token = $this->authenticateAsAdmin();
        $this->subscription()->setEvents('not json')->save();
        $this->subscription();

        $response = $this->jsonRequest('GET', self::URI, token: $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $events = array_column(json_decode((string) $response->getContent(), true)['hydra:member'], 'events');
        self::assertContains([], $events);
        self::assertContains(['order.created'], $events);
    }

    public function testNoReadEverCarriesTheSecret(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();

        foreach ([self::URI, self::URI.'/'.$subscription->getId()] as $uri) {
            $response = $this->jsonRequest('GET', $uri, token: $token);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringNotContainsString($subscription->getSecret(), (string) $response->getContent(), $uri);
            self::assertStringNotContainsString('"secret"', (string) $response->getContent(), $uri);
        }
    }

    public function testAnUpdateCannotWriteTheSecret(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        $secret = $subscription->getSecret();

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['secret' => str_repeat('a', 64), 'title' => 'Renamed'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $stored = $this->reread($subscription);
        self::assertSame('Renamed', $stored->getTitle());
        self::assertSame($secret, $stored->getSecret());
    }

    public function testAPartialUpdateKeepsWhatItDoesNotCarry(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription(WebhookStatus::SUBSCRIPTION_INACTIVE, WebhookStatus::PAYLOAD_IDENTIFIERS);

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['title' => 'Only the title'], $token, 'merge-patch+json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $stored = $this->reread($subscription);
        self::assertSame('Only the title', $stored->getTitle());
        self::assertSame(WebhookStatus::PAYLOAD_IDENTIFIERS, $stored->getPayloadMode());
        self::assertSame(WebhookStatus::SUBSCRIPTION_INACTIVE, $stored->getStatus());
        self::assertSame(['order.created'], $stored->getEventNames());
    }

    public function testASubscriptionIsSwitchedOffAndBackOn(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();

        $off = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['status' => 'inactive'], $token, 'merge-patch+json');
        self::assertSame(200, $off->getStatusCode(), (string) $off->getContent());
        self::assertSame(WebhookStatus::SUBSCRIPTION_INACTIVE, $this->reread($subscription)->getStatus());

        $on = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['status' => 'active'], $token, 'merge-patch+json');
        self::assertSame(200, $on->getStatusCode(), (string) $on->getContent());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->reread($subscription)->getStatus());
    }

    public function testRenamingASubscriptionInErrorKeepsTheErrorAndEnablingItLiftsIt(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription(WebhookStatus::SUBSCRIPTION_ERROR);

        $renamed = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['title' => 'Still broken'], $token, 'merge-patch+json');
        self::assertSame(200, $renamed->getStatusCode(), (string) $renamed->getContent());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ERROR, $this->reread($subscription)->getStatus());
        self::assertNotNull($this->reread($subscription)->getFailureReason());

        $enabled = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['status' => 'active'], $token, 'merge-patch+json');
        self::assertSame(200, $enabled->getStatusCode(), (string) $enabled->getContent());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->reread($subscription)->getStatus());
        self::assertNull($this->reread($subscription)->getFailureReason());
    }

    public function testAClientCannotPutASubscriptionInError(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['status' => 'error'], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $this->reread($subscription)->getStatus());
    }

    public function testTheSecretIsReplacedAndHandedOverOnce(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        $old = $subscription->getSecret();

        $response = $this->jsonRequest('POST', self::URI.'/'.$subscription->getId().'/secret', token: $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $data['secret']);
        self::assertNotSame($old, $data['secret']);
        self::assertSame($data['secret'], $this->reread($subscription)->getSecret());

        $read = $this->jsonRequest('GET', self::URI.'/'.$subscription->getId(), token: $token);
        self::assertStringNotContainsString($data['secret'], (string) $read->getContent());
    }

    public function testReplacingTheSecretOfAnUnknownSubscriptionIsANotFound(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertSame(404, $this->jsonRequest('POST', self::URI.'/99999999/secret', token: $token)->getStatusCode());
    }

    public function testDeletingASubscriptionDeletesItsDeliveries(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();
        $this->delivery($subscription);

        $response = $this->jsonRequest('DELETE', self::URI.'/'.$subscription->getId(), token: $token);

        self::assertSame(204, $response->getStatusCode());
        self::assertNull(WebhookSubscriptionQuery::create()->findPk($subscription->getId()));
        self::assertSame(0, WebhookDeliveryQuery::create()->filterBySubscriptionId($subscription->getId())->count());
        self::assertSame(404, $this->jsonRequest('DELETE', self::URI.'/'.$subscription->getId(), token: $token)->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedBodies(): iterable
    {
        $valid = ['title' => 'ERP', 'url' => self::RECEIVER, 'events' => ['order.created']];

        yield 'private network' => [[...$valid, 'url' => 'https://10.0.0.5/hook']];
        yield 'shared address space' => [[...$valid, 'url' => 'https://100.64.0.1/hook']];
        yield 'loopback name' => [[...$valid, 'url' => 'https://localhost/hook']];
        yield 'plain http' => [[...$valid, 'url' => 'http://hooks.example.com/in']];
        yield 'credentials in the address' => [[...$valid, 'url' => 'https://user:pass@hooks.example.com/in']];
        yield 'no address' => [['title' => 'ERP', 'events' => ['order.created']]];
        yield 'no title' => [['url' => self::RECEIVER, 'events' => ['order.created']]];
        yield 'blank title' => [[...$valid, 'title' => '   ']];
        yield 'no event' => [[...$valid, 'events' => []]];
        yield 'unknown event' => [[...$valid, 'events' => ['order.exploded']]];
        yield 'event that is not a name' => [[...$valid, 'events' => [42]]];
        yield 'unknown payload mode' => [[...$valid, 'payloadMode' => 'everything']];
        yield 'state error' => [[...$valid, 'status' => 'error']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('refusedBodies')]
    public function testAnInvalidSubscriptionIsRefusedAndNothingIsStored(array $body): void
    {
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', self::URI, $body, $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, WebhookSubscriptionQuery::create()->count());
    }

    public function testAnAddressOnAPrivateNetworkIsRefusedOnUpdateToo(): void
    {
        $token = $this->authenticateAsAdmin();
        $subscription = $this->subscription();

        $response = $this->jsonRequest('PATCH', self::URI.'/'.$subscription->getId(), ['url' => 'https://192.168.1.1/hook'], $token, 'merge-patch+json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(self::RECEIVER, $this->reread($subscription)->getUrl());
    }

    public function testAnAdminWithoutTheWebhookPermissionIsRefusedOnEveryOperation(): void
    {
        $subscription = $this->subscription();
        $secret = $subscription->getSecret();
        $token = $this->authenticateAsAdmin($this->adminWithout());

        foreach ($this->operations($subscription) as $label => [$method, $uri, $body, $format]) {
            $response = $this->jsonRequest($method, $uri, $body, $token, $format);

            self::assertSame(403, $response->getStatusCode(), $label.': '.$response->getContent());
        }

        $stored = $this->reread($subscription);
        self::assertSame($secret, $stored->getSecret());
        self::assertSame('Existing', $stored->getTitle());
        self::assertSame(1, WebhookSubscriptionQuery::create()->count());
    }

    public function testAReaderCanReadButNotWrite(): void
    {
        $subscription = $this->subscription();
        $reader = $this->createFixtureFactory()->restrictedAdmin([AdminResources::WEBHOOK => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($reader);

        self::assertSame(200, $this->jsonRequest('GET', self::URI, token: $token)->getStatusCode());
        self::assertSame(200, $this->jsonRequest('GET', self::URI.'/'.$subscription->getId(), token: $token)->getStatusCode());

        foreach ($this->operations($subscription) as $label => [$method, $uri, $body, $format]) {
            if ('GET' === $method) {
                continue;
            }

            self::assertSame(403, $this->jsonRequest($method, $uri, $body, $token, $format)->getStatusCode(), $label);
        }

        self::assertSame(1, WebhookSubscriptionQuery::create()->count());
    }

    public function testReplacingTheSecretNeedsTheRightToUpdateNotOnlyToCreate(): void
    {
        $subscription = $this->subscription();
        $secret = $subscription->getSecret();
        $creator = $this->createFixtureFactory()->restrictedAdmin([AdminResources::WEBHOOK => [AccessManager::VIEW, AccessManager::CREATE]]);
        $token = $this->authenticateAsAdmin($creator);

        $response = $this->jsonRequest('POST', self::URI.'/'.$subscription->getId().'/secret', token: $token);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame($secret, $this->reread($subscription)->getSecret());
    }

    public function testAnAdminGrantedTheWebhookResourceCanManageSubscriptions(): void
    {
        $manager = $this->createFixtureFactory()->restrictedAdmin([AdminResources::WEBHOOK => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]]);
        $token = $this->authenticateAsAdmin($manager);

        $created = $this->jsonRequest('POST', self::URI, ['title' => 'ERP', 'url' => self::RECEIVER, 'events' => ['order.created']], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
    }

    public function testNobodyWithoutAnAdminTokenReachesAnOperation(): void
    {
        $subscription = $this->subscription();
        $customerToken = $this->authenticateAsCustomer();

        foreach ($this->operations($subscription) as $label => [$method, $uri, $body, $format]) {
            self::assertSame(401, $this->jsonRequest($method, $uri, $body, null, $format)->getStatusCode(), $label.' without a token');
            self::assertSame(403, $this->jsonRequest($method, $uri, $body, $customerToken, $format)->getStatusCode(), $label.' as a customer');
        }

        self::assertSame(1, WebhookSubscriptionQuery::create()->count());
        self::assertSame('Existing', $this->reread($subscription)->getTitle());
    }

    public function testNoFrontOperationExists(): void
    {
        $subscription = $this->subscription();
        $token = $this->authenticateAsAdmin();

        foreach (['/api/front/webhook-subscriptions', '/api/front/webhook-subscriptions/'.$subscription->getId(), '/api/front/account/webhook-subscriptions', '/api/webhook-subscriptions', '/api/front/webhook-deliveries'] as $uri) {
            self::assertSame(404, $this->jsonRequest('GET', $uri, token: $token)->getStatusCode(), $uri);
        }
    }

    public function testTheCollectionPageIsCappedBelowTheGlobalCeiling(): void
    {
        $token = $this->authenticateAsAdmin();
        for ($i = 0; $i < 55; ++$i) {
            $this->subscription();
        }

        $response = $this->jsonRequest('GET', self::URI.'?itemsPerPage=100000', token: $token);

        $data = json_decode((string) $response->getContent(), true);
        self::assertCount(50, $data['hydra:member']);
        self::assertSame(55, $data['hydra:totalItems']);
        self::assertCount(25, json_decode((string) $this->jsonRequest('GET', self::URI, token: $token)->getContent(), true)['hydra:member']);
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>, string}>
     */
    private function operations(WebhookSubscription $subscription): array
    {
        $item = self::URI.'/'.$subscription->getId();

        return [
            'list' => ['GET', self::URI, [], 'jsonld'],
            'read' => ['GET', $item, [], 'jsonld'],
            'create' => ['POST', self::URI, ['title' => 'Intruder', 'url' => self::RECEIVER, 'events' => ['order.created']], 'jsonld'],
            'update' => ['PATCH', $item, ['title' => 'Hijacked'], 'merge-patch+json'],
            'secret' => ['POST', $item.'/secret', [], 'jsonld'],
            'delete' => ['DELETE', $item, [], 'jsonld'],
        ];
    }

    private function adminWithout(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE],
        ]);
    }

    private function subscription(string $status = WebhookStatus::SUBSCRIPTION_ACTIVE, string $mode = WebhookStatus::PAYLOAD_FULL): WebhookSubscription
    {
        $subscription = (new WebhookSubscription())
            ->setTitle('Existing')
            ->setUrl(self::RECEIVER)
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEvents('["order.created"]')
            ->setPayloadMode($mode)
            ->setStatus($status);

        if (WebhookStatus::SUBSCRIPTION_ERROR === $status) {
            $subscription->setFailureReason('Delivery x failed after 5 attempts.')->setFailedAt(new \DateTimeImmutable());
        }

        $subscription->save();

        return $subscription;
    }

    private function delivery(WebhookSubscription $subscription): WebhookDelivery
    {
        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent('order.created')
            ->setDeliveryUuid(bin2hex(random_bytes(18)))
            ->setPayload('{"id":"x"}')
            ->setStatus(WebhookStatus::DELIVERY_FAILED)
            ->setAttempts(1);
        $delivery->save();

        return $delivery;
    }

    private function reread(WebhookSubscription $subscription): WebhookSubscription
    {
        return WebhookSubscriptionQuery::create()->findPk($subscription->getId()) ?? throw new \LogicException('The subscription is gone.');
    }
}
