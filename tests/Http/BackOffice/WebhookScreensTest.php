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

namespace Thelia\Tests\Http\BackOffice;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Webhook\WebhookStatus;
use Thelia\Model\Admin;
use Thelia\Model\WebhookDelivery;
use Thelia\Model\WebhookDeliveryQuery;
use Thelia\Model\WebhookSubscription;
use Thelia\Model\WebhookSubscriptionQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

final class WebhookScreensTest extends WebIntegrationTestCase
{
    private const LIST_URL = '/admin/configuration/webhooks';
    private const JOURNAL_URL = '/admin/configuration/webhooks/deliveries';
    private const RECEIVER = 'https://hooks.example.com/in';

    private ?AdminSessionInjector $injector = null;

    /** @var list<string> */
    private array $requested = [];
    private int $answerCode = 200;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\Configuration\\WebhookSubscriptionController')) {
            self::markTestSkipped('The installed back-office theme has no webhook screens.');
        }

        $this->requested = [];
        $this->answerCode = 200;
        $this->getContainer()->set('thelia.webhook.http_client', new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requested[] = $url;

            return new MockResponse('ok', ['http_code' => $this->answerCode]);
        }));

        WebhookSubscriptionQuery::create()->deleteAll($this->getPropelConnection());

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testAnAdministratorWithoutTheWebhookPermissionIsRefusedEverywhere(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->restrictedAdmin(['admin.configuration' => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]]));

        foreach ([
            ['GET', self::LIST_URL],
            ['GET', self::JOURNAL_URL],
            ['GET', self::JOURNAL_URL.'/'.$delivery->getId()],
            ['GET', self::LIST_URL.'/update/'.$subscription->getId()],
            ['POST', self::LIST_URL.'/create'],
            ['POST', self::LIST_URL.'/save'],
            ['POST', self::LIST_URL.'/delete'],
            ['POST', self::LIST_URL.'/rotate-secret'],
            ['POST', self::LIST_URL.'/test'],
            ['POST', self::JOURNAL_URL.'/replay'],
        ] as [$method, $url]) {
            $this->client->request($method, $url, ['webhook_id' => $subscription->getId(), 'delivery_id' => $delivery->getId()]);
            self::assertSame(403, $this->client->getResponse()->getStatusCode(), $method.' '.$url.' must be refused.');
        }

        self::assertSame(1, WebhookSubscriptionQuery::create()->count());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, WebhookDeliveryQuery::create()->findPk($delivery->getId())?->getStatus());
        self::assertSame([], $this->requested);
    }

    public function testNoActionThatWritesOrSendsAnswersToAGet(): void
    {
        $this->loginAs($this->factory()->admin());

        foreach ([
            self::LIST_URL.'/create',
            self::LIST_URL.'/save',
            self::LIST_URL.'/delete',
            self::LIST_URL.'/rotate-secret',
            self::LIST_URL.'/test',
            self::JOURNAL_URL.'/replay',
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertSame(405, $this->client->getResponse()->getStatusCode(), 'GET '.$url.' must not reach the action.');
        }

        self::assertSame([], $this->requested);
    }

    public function testCreatingASubscriptionShowsTheSecretOnceAndNeverInTheList(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->createThrough(['title' => 'ERP', 'url' => self::RECEIVER, 'events' => ['order.created', 'order.paid']]);

        $stored = WebhookSubscriptionQuery::create()->findOneByTitle('ERP');
        self::assertNotNull($stored);
        self::assertSame(['order.created', 'order.paid'], json_decode($stored->getEvents(), true));
        $this->client->followRedirect();
        $shown = $this->client->getCrawler()->filter('[data-testid="webhook-secret-value"]');
        self::assertCount(1, $shown, 'The secret is shown on the page the creation lands on.');
        self::assertSame($stored->getSecret(), $shown->attr('value'));

        $this->assertPageRenders(self::LIST_URL.'/update/'.$stored->getId());
        self::assertCount(0, $this->client->getCrawler()->filter('[data-testid="webhook-secret-value"]'), 'A second visit does not show it again.');
        self::assertStringNotContainsString($stored->getSecret(), (string) $this->client->getResponse()->getContent());

        $this->assertPageRenders(self::LIST_URL);
        self::assertStringNotContainsString($stored->getSecret(), (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('ERP', (string) $this->client->getResponse()->getContent());
    }

    public function testAnAddressOnAPrivateNetworkIsRefusedByTheServer(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->createThrough(['title' => 'Inside', 'url' => 'https://10.0.0.5/in', 'events' => ['order.created']]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, WebhookSubscriptionQuery::create()->count());
        $this->client->followRedirect();
        self::assertStringContainsString('not a public address', (string) $this->client->getResponse()->getContent());
    }

    public function testRegeneratingTheSecretShowsTheNewOneOnceAndRetiresTheOldOne(): void
    {
        $subscription = $this->subscription();
        $old = $subscription->getSecret();
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $form = $this->client->getCrawler()->filter('form[action$="/webhooks/rotate-secret"]')->form();
        $this->client->submit($form, ['webhook_id' => $subscription->getId()]);

        $new = WebhookSubscriptionQuery::create()->findPk($subscription->getId())?->getSecret();
        self::assertNotSame($old, $new);
        $this->client->followRedirect();
        self::assertSame($new, $this->client->getCrawler()->filter('[data-testid="webhook-secret-value"]')->attr('value'));
    }

    public function testDeletingWithoutTheTokenDeletesNothing(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin());

        $this->client->request('POST', self::LIST_URL.'/delete', ['webhook_id' => $subscription->getId()]);

        self::assertNotNull(WebhookSubscriptionQuery::create()->findPk($subscription->getId()));

        $this->assertPageRenders(self::LIST_URL);
        $form = $this->client->getCrawler()->filter('form[data-testid="webhook-delete-form"]')->form();
        $this->client->submit($form, ['webhook_id' => $subscription->getId()]);

        self::assertNull(WebhookSubscriptionQuery::create()->findPk($subscription->getId()));
    }

    public function testTheTestButtonSendsASignedRequestAndLogsIt(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin(['login' => 'webhook-test-'.bin2hex(random_bytes(4))]));

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/webhooks/test"]')->form());

        self::assertSame([self::RECEIVER], $this->requested);
        $logged = WebhookDeliveryQuery::create()->filterByEvent('webhook.test')->findOne();
        self::assertNotNull($logged);
        self::assertSame(WebhookStatus::DELIVERY_SUCCEEDED, $logged->getStatus());
    }

    public function testTheTestButtonRefusesAMissingToken(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin());

        $this->client->request('POST', self::LIST_URL.'/test', ['webhook_id' => $subscription->getId()]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->requested);
        self::assertSame(0, WebhookDeliveryQuery::create()->count());
        $this->client->followRedirect();
        self::assertStringContainsString('Invalid security token', (string) $this->client->getResponse()->getContent());
    }

    public function testTheJournalEscapesWhatAHostileReceiverAnswered(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED, '<script>alert("answer")</script>', '{"note":"<img src=x onerror=alert(1)>"}');
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert("answer")</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;answer&quot;)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<img src=x onerror', $html);
    }

    public function testTheJournalFiltersBySubscriptionEventAndStatus(): void
    {
        $first = $this->subscription('First');
        $second = $this->subscription('Second');
        $this->delivery($first, WebhookStatus::DELIVERY_FAILED);
        $this->delivery($first, WebhookStatus::DELIVERY_SUCCEEDED);
        $this->delivery($second, WebhookStatus::DELIVERY_FAILED, event: 'order.paid');
        $this->loginAs($this->factory()->admin());

        self::assertSame(3, $this->journalRows(''));
        self::assertSame(2, $this->journalRows('?subscription_id='.$first->getId()));
        self::assertSame(2, $this->journalRows('?status=failed'));
        self::assertSame(1, $this->journalRows('?event=order.paid'));
        self::assertSame(1, $this->journalRows('?subscription_id='.$first->getId().'&status=failed'));
    }

    public function testTheJournalIsPaginated(): void
    {
        $subscription = $this->subscription();
        for ($index = 0; $index < 27; ++$index) {
            $this->delivery($subscription, WebhookStatus::DELIVERY_SUCCEEDED);
        }
        $this->loginAs($this->factory()->admin());

        self::assertSame(25, $this->journalRows(''));
        self::assertSame(2, $this->journalRows('?page=2'));
        self::assertCount(1, $this->client->getCrawler()->filter('[data-testid="webhook-deliveries-pagination"]'));
    }

    public function testReplayingAFailedDeliverySendsItAgain(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/deliveries/replay"]')->form());

        self::assertSame([self::RECEIVER], $this->requested);
        self::assertSame(WebhookStatus::DELIVERY_SUCCEEDED, WebhookDeliveryQuery::create()->findPk($delivery->getId())?->getStatus());
    }

    public function testNoReplayIsOfferedForASubscriptionThatIsNotActive(): void
    {
        $subscription = $this->subscription('Silenced', WebhookStatus::SUBSCRIPTION_ERROR);
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());

        self::assertCount(0, $this->client->getCrawler()->filter('[data-testid="webhook-replay-button"]'));
        self::assertCount(1, $this->client->getCrawler()->filter('[data-testid="webhook-replay-refused"]'));

        $this->assertPageRenders(self::LIST_URL);
        $token = $this->tokenFromPage();
        $this->client->request('POST', self::JOURNAL_URL.'/replay', ['delivery_id' => $delivery->getId(), '_token' => $token]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $this->client->followRedirect();
        self::assertStringContainsString('enable it before replaying a delivery', (string) $this->client->getResponse()->getContent());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, WebhookDeliveryQuery::create()->findPk($delivery->getId())?->getStatus());
        self::assertSame([], $this->requested);
    }

    public function testTheEditPageIsNeverCached(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());

        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testTheErrorStateIsOfferedOnlyToASubscriptionThatIsInError(): void
    {
        $healthy = $this->subscription('Healthy');
        $broken = $this->subscription('Broken', WebhookStatus::SUBSCRIPTION_ERROR);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$healthy->getId());
        self::assertSame(['active', 'inactive'], $this->statusChoices());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$broken->getId());
        self::assertSame(['active', 'inactive', 'error'], $this->statusChoices());
    }

    public function testSavingFromTheScreenUpdatesTheSubscriptionAndLiftsTheError(): void
    {
        $subscription = $this->subscription('Before', WebhookStatus::SUBSCRIPTION_ERROR);
        $secret = $subscription->getSecret();
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $form = $this->client->getCrawler()->filter('form[action$="/webhooks/save"]')->form();
        $values = $form->getPhpValues();
        $values['thelia_webhook_update']['title'] = 'After';
        $values['thelia_webhook_update']['events'] = ['customer.created'];
        $values['thelia_webhook_update']['status'] = WebhookStatus::SUBSCRIPTION_ACTIVE;
        $this->client->request('POST', $form->getUri(), $values);

        $stored = WebhookSubscriptionQuery::create()->findPk($subscription->getId());
        self::assertSame('After', $stored?->getTitle());
        self::assertSame('["customer.created"]', $stored?->getEvents());
        self::assertSame(WebhookStatus::SUBSCRIPTION_ACTIVE, $stored?->getStatus());
        self::assertNull($stored?->getFailureReason());
        self::assertSame($secret, $stored?->getSecret());
    }

    public function testSavingAnErrorSubscriptionWithoutTouchingItsStateKeepsTheError(): void
    {
        $subscription = $this->subscription('Broken', WebhookStatus::SUBSCRIPTION_ERROR);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/webhooks/save"]')->form());

        self::assertSame(WebhookStatus::SUBSCRIPTION_ERROR, WebhookSubscriptionQuery::create()->findPk($subscription->getId())?->getStatus());
    }

    public function testTheListShowsTheLastSuccessfulDeliveryOrNever(): void
    {
        $delivered = $this->subscription('Delivered one');
        $this->subscription('Silent one');
        $this->delivery($delivered, WebhookStatus::DELIVERY_FAILED);
        $success = $this->delivery($delivered, WebhookStatus::DELIVERY_SUCCEEDED);
        $success->setDeliveredAt(new \DateTimeImmutable('2026-03-04 05:06:07'))->save($this->getPropelConnection());
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL);

        $rows = $this->client->getCrawler()->filter('[data-testid="datatable-webhooks"] tbody tr');
        $text = [];
        foreach ($rows as $row) {
            $text[] = preg_replace('/\s+/', ' ', trim($row->textContent));
        }
        $delivered = array_values(array_filter($text, static fn (string $row): bool => str_contains($row, 'Delivered one')));
        $silent = array_values(array_filter($text, static fn (string $row): bool => str_contains($row, 'Silent one')));
        self::assertStringContainsString('2026-03-04 05:06:07', $delivered[0] ?? '');
        self::assertStringContainsString('Never', $silent[0] ?? '');
    }

    public function testTheTestRequestsOfAScreenAreNotCountedAsDeliveries(): void
    {
        $subscription = $this->subscription('Only tested');
        $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED, event: 'webhook.test');
        $this->delivery($subscription, WebhookStatus::DELIVERY_SUCCEEDED, event: 'webhook.test')
            ->setDeliveredAt(new \DateTimeImmutable('2026-03-04 05:06:07'))->save($this->getPropelConnection());
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::LIST_URL);
        $rows = array_map(
            static fn (\DOMNode $row): string => preg_replace('/\s+/', ' ', trim($row->textContent)),
            iterator_to_array($this->client->getCrawler()->filter('[data-testid="datatable-webhooks"] tbody tr')),
        );
        $row = array_values(array_filter($rows, static fn (string $text): bool => str_contains($text, 'Only tested')));
        self::assertStringContainsString('Never', $row[0] ?? '');
        self::assertStringNotContainsString('2026-03-04', $row[0] ?? '');

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        self::assertStringNotContainsString('failed deliveries', (string) $this->client->getResponse()->getContent());
    }

    public function testTheEditPageOffersNoSaveButtonToAReader(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->restrictedAdmin(['admin.configuration.webhook' => [AccessManager::VIEW]]));

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());

        self::assertCount(0, $this->client->getCrawler()->filter('[data-testid="webhook-save"]'));
    }

    public function testAReplayIsReportedAsQueuedNotAsSent(): void
    {
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/deliveries/replay"]')->form());
        $this->client->followRedirect();

        self::assertStringContainsString('queued again', (string) $this->client->getResponse()->getContent());
    }

    public function testAnAdministratorWhoOnlyReadsCannotWriteOrSend(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->restrictedAdmin(['admin.configuration.webhook' => [AccessManager::VIEW]]));

        $this->assertPageRenders(self::LIST_URL);
        $this->assertPageRenders(self::JOURNAL_URL);
        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());
        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());

        foreach ([
            self::LIST_URL.'/create',
            self::LIST_URL.'/save',
            self::LIST_URL.'/delete',
            self::LIST_URL.'/rotate-secret',
            self::LIST_URL.'/test',
            self::JOURNAL_URL.'/replay',
        ] as $url) {
            $this->client->request('POST', $url, ['webhook_id' => $subscription->getId(), 'delivery_id' => $delivery->getId()]);
            self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'POST '.$url.' must be refused to a reader.');
        }

        self::assertSame([], $this->requested);
        self::assertSame(1, WebhookSubscriptionQuery::create()->count());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, WebhookDeliveryQuery::create()->findPk($delivery->getId())?->getStatus());
    }

    public function testAFailedTestRequestTellsWhatTheReceiverAnswered(): void
    {
        $this->answerCode = 500;
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin(['login' => 'webhook-fail-'.bin2hex(random_bytes(4))]));

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $this->client->submit($this->client->getCrawler()->filter('form[action$="/webhooks/test"]')->form());
        $this->client->followRedirect();

        self::assertStringContainsString('The receiver answered 500', (string) $this->client->getResponse()->getContent());
        self::assertSame(WebhookStatus::DELIVERY_FAILED, WebhookDeliveryQuery::create()->filterByEvent('webhook.test')->findOne()?->getStatus());
    }

    public function testTheTestButtonIsThrottledPerAdministrator(): void
    {
        $subscription = $this->subscription();
        $this->loginAs($this->factory()->admin(['login' => 'webhook-throttle-'.bin2hex(random_bytes(4))]));

        $this->assertPageRenders(self::LIST_URL.'/update/'.$subscription->getId());
        $form = $this->client->getCrawler()->filter('form[action$="/webhooks/test"]')->form();
        for ($attempt = 0; $attempt < 11; ++$attempt) {
            $this->client->submit($form);
        }

        self::assertCount(10, $this->requested, 'The eleventh test request is held back.');
        $this->client->followRedirect();
        self::assertStringContainsString('Too many test requests', (string) $this->client->getResponse()->getContent());
    }

    public function testReplaysAreThrottledPerAdministrator(): void
    {
        $subscription = $this->subscription();
        $delivery = $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->loginAs($this->factory()->admin(['login' => 'webhook-replays-'.bin2hex(random_bytes(4))]));

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());
        $form = $this->client->getCrawler()->filter('form[action$="/deliveries/replay"]')->form();
        for ($attempt = 0; $attempt < 21; ++$attempt) {
            $this->client->submit($form);
        }

        $this->client->followRedirect();
        self::assertStringContainsString('Too many replays', (string) $this->client->getResponse()->getContent());
        self::assertCount(1, $this->requested, 'Only the first replay was a failed delivery; the others were refused before sending.');
    }

    public function testTheJournalIgnoresWhatItDoesNotKnow(): void
    {
        $subscription = $this->subscription();
        $this->delivery($subscription, WebhookStatus::DELIVERY_FAILED);
        $this->delivery($subscription, WebhookStatus::DELIVERY_SUCCEEDED);
        $this->loginAs($this->factory()->admin());

        self::assertSame(2, $this->journalRows('?status=bogus&event=nothing&subscription_id=abc'));
        self::assertSame(2, $this->journalRows('?page=999'));
        self::assertSame(2, $this->journalRows('?page=-4'));
    }

    public function testTheDetailShowsTheSignedBodyAsItWasSent(): void
    {
        $delivery = $this->delivery($this->subscription(), WebhookStatus::DELIVERY_SUCCEEDED, null, '{"data":{},"ratio":12.0}');
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::JOURNAL_URL.'/'.$delivery->getId());

        $body = $this->client->getCrawler()->filter('[data-testid="webhook-delivery-payload"]')->text();
        self::assertStringContainsString('"data": {}', $body);
        self::assertStringContainsString('"ratio": 12.0', $body);
    }

    /**
     * @return list<string>
     */
    private function statusChoices(): array
    {
        $values = [];
        foreach ($this->client->getCrawler()->filter('select[name="thelia_webhook_update[status]"] option') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }

    /**
     * @param array{title: string, url: string, events: list<string>} $fields
     */
    private function createThrough(array $fields): void
    {
        $this->assertPageRenders(self::LIST_URL);
        $form = $this->client->getCrawler()->filter('form[action$="/webhooks/create"]')->form();
        $values = $form->getPhpValues();
        $values['thelia_webhook_create'] = array_merge($values['thelia_webhook_create'] ?? [], $fields, ['payload_mode' => WebhookStatus::PAYLOAD_FULL, 'status' => WebhookStatus::SUBSCRIPTION_ACTIVE]);

        $this->client->request('POST', $form->getUri(), $values);
    }

    private function journalRows(string $query): int
    {
        $this->assertPageRenders(self::JOURNAL_URL.$query);

        return $this->client->getCrawler()->filter('[data-testid="datatable-webhook-deliveries"] tbody tr')->count();
    }

    private function tokenFromPage(): string
    {
        return (string) $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');
    }

    private function subscription(string $title = 'Existing', string $status = WebhookStatus::SUBSCRIPTION_ACTIVE): WebhookSubscription
    {
        $subscription = (new WebhookSubscription())
            ->setTitle($title)
            ->setUrl(self::RECEIVER)
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEvents('["order.created"]')
            ->setPayloadMode(WebhookStatus::PAYLOAD_FULL)
            ->setStatus($status);
        $subscription->save($this->getPropelConnection());

        return $subscription;
    }

    private function delivery(WebhookSubscription $subscription, string $status, ?string $answer = null, string $payload = '{"id":"x"}', string $event = 'order.created'): WebhookDelivery
    {
        $delivery = (new WebhookDelivery())
            ->setWebhookSubscription($subscription)
            ->setEvent($event)
            ->setDeliveryUuid(bin2hex(random_bytes(18)))
            ->setPayload($payload)
            ->setStatus($status)
            ->setAttempts(1)
            ->setResponseBody($answer);
        $delivery->save($this->getPropelConnection());

        return $delivery;
    }

    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
