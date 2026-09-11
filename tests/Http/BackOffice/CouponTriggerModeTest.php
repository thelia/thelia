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

use BackOfficeDefaultTwigBundle\Service\Coupon\CouponTriggerModeInput;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the automatic cart promotions US: the "with a code /
 * automatic" choice on the coupon editor
 * (BackOfficeDefaultTwigBundle\Service\Coupon\CouponTriggerModeInput, applied by
 * that theme's CouponController::handleCreateOrUpdate) and the trigger-mode
 * filter on the coupon list.
 */
final class CouponTriggerModeTest extends WebIntegrationTestCase
{
    private const COUPON_TYPE = 'thelia.coupon.type.remove_x_amount';

    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office
        // theme it is given, and one that predates the automatic promotions has
        // no trigger-mode choice to assert on.
        if (!class_exists(CouponTriggerModeInput::class)) {
            self::markTestSkipped('The installed back-office theme predates the automatic promotions.');
        }

        parent::setUp();

        $this->skipUnlessTwigBackOffice();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    /**
     * The class of the theme is on the autoloader as soon as it is installed,
     * whether or not it is the template the store actually runs: the screens
     * asserted here only exist when it is the active one.
     */
    private function skipUnlessTwigBackOffice(): void
    {
        $template = static::getContainer()->getParameter('thelia_admin_template');

        if ('default-twig' !== $template) {
            self::markTestSkipped(\sprintf(
                'The installed back-office template is "%s", not "default-twig".',
                \is_string($template) ? $template : 'unknown',
            ));
        }
    }

    protected function tearDown(): void
    {
        // setUp() may have skipped before wiring the injector.
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        // FixtureFactory built directly (not via createFixtureFactory()): that
        // helper pushes a synthetic Request onto the stack, which would then
        // become the "main" request the security context resolves the session
        // from for every subsequent client->request() call in this test.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    /**
     * The coupon form posts a session-wide token (Thelia\Tools\TokenProvider),
     * so any rendered coupon screen provides a token valid for the next post.
     */
    private function couponFormToken(string $url): string
    {
        $this->assertPageRenders($url);

        $token = $this->client->getCrawler()->filter('form[data-testid="coupon-edit-form"] input[name="_token"]');
        self::assertGreaterThan(0, $token->count(), 'The coupon editor must render a CSRF token this test can reuse.');

        return (string) $token->attr('value');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function couponPayload(string $token, array $overrides = []): array
    {
        return array_replace([
            '_token' => $token,
            'locale' => 'en_US',
            'type' => self::COUPON_TYPE,
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => '',
            'title' => '',
            'shortDescription' => '',
            'description' => '',
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => ['amount' => '5'],
            'save_mode' => 'close',
        ], $overrides);
    }

    private function newestCoupon(): ?Coupon
    {
        return CouponQuery::create()->orderById(Criteria::DESC)->findOne();
    }

    public function testAnAutomaticPromotionIsCreatedWithoutAnyCode(): void
    {
        $this->loginAdmin();

        $token = $this->couponFormToken('/admin/coupon/create');
        $title = 'Automatic promotion '.uniqid();

        $this->client->request('POST', '/admin/coupon/create', $this->couponPayload($token, [
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'title' => $title,
        ]));

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Creating a valid automatic promotion must redirect (a 200 here means the form was rejected).',
        );

        $created = $this->newestCoupon();
        self::assertNotNull($created);
        self::assertSame($title, $created->getTitle());
        self::assertSame(Coupon::TRIGGER_MODE_AUTOMATIC, $created->getTriggerMode());
        self::assertTrue($created->isAutomatic());
        self::assertNull($created->getCode(), 'An automatic promotion carries no code at all, not an empty one.');
    }

    public function testACouponWithACodeIsRejectedWhenTheCodeIsMissing(): void
    {
        $this->loginAdmin();

        $before = (int) CouponQuery::create()->count();
        $token = $this->couponFormToken('/admin/coupon/create');

        $this->client->request('POST', '/admin/coupon/create', $this->couponPayload($token, [
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => '',
            'title' => 'Coupon without a code '.uniqid(),
        ]));

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'A coupon posted with no code must come back to the form, not redirect.',
        );
        self::assertSame($before, (int) CouponQuery::create()->count(), 'Nothing must have been persisted.');
    }

    public function testAnAutomaticPromotionIsRejectedWhenTheTitleIsMissing(): void
    {
        $this->loginAdmin();

        $before = (int) CouponQuery::create()->count();
        $token = $this->couponFormToken('/admin/coupon/create');

        $this->client->request('POST', '/admin/coupon/create', $this->couponPayload($token, [
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'title' => '',
        ]));

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'The title is the label the buyer sees: an automatic promotion without one must be refused.',
        );
        self::assertSame($before, (int) CouponQuery::create()->count(), 'Nothing must have been persisted.');
    }

    public function testAnExistingCouponSwitchesBetweenBothModes(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $coupon = $factory->coupon(['code' => 'SWITCH-'.strtoupper(uniqid()), 'title' => 'Switchable coupon']);
        $couponId = (int) $coupon->getId();
        $updateUrl = '/admin/coupon/update/'.$couponId;

        $token = $this->couponFormToken($updateUrl);

        // Code -> automatic: the code is dropped.
        $this->client->request('POST', $updateUrl, $this->couponPayload($token, [
            'trigger_mode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'code' => (string) $coupon->getCode(),
            'title' => 'Switchable coupon',
            'save_mode' => 'stay',
        ]));

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $automatic = CouponQuery::create()->findPk($couponId);
        self::assertNotNull($automatic);
        self::assertSame(Coupon::TRIGGER_MODE_AUTOMATIC, $automatic->getTriggerMode());
        self::assertNull($automatic->getCode());

        // Automatic -> code: the merchant gives it a code again.
        $newCode = 'BACK-'.strtoupper(uniqid());
        $this->client->request('POST', $updateUrl, $this->couponPayload($token, [
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => $newCode,
            'title' => 'Switchable coupon',
            'save_mode' => 'stay',
        ]));

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $backToCode = CouponQuery::create()->findPk($couponId);
        self::assertNotNull($backToCode);
        self::assertSame(Coupon::TRIGGER_MODE_CODE, $backToCode->getTriggerMode());
        self::assertSame($newCode, $backToCode->getCode());
    }

    public function testTheListFiltersOnTheTriggerMode(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());

        $withCode = $factory->coupon(['code' => 'LISTFILTER-'.strtoupper(uniqid())]);
        $automaticTitle = 'Listed automatic promotion '.uniqid();
        $automatic = $factory->coupon(['title' => $automaticTitle]);
        $automatic
            ->setCode(null)
            ->setTriggerMode(Coupon::TRIGGER_MODE_AUTOMATIC)
            ->save($this->getPropelConnection());

        // Newest first, so both fixtures sit on the first page whatever the
        // catalogue already holds.
        $this->assertPageRenders('/admin/coupon?mode=automatic&order=id&direction=desc');
        $automaticOnly = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($automaticTitle, $automaticOnly);
        self::assertStringNotContainsString((string) $withCode->getCode(), $automaticOnly);

        $this->assertPageRenders('/admin/coupon?mode=code&order=id&direction=desc');
        $codeOnly = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString((string) $withCode->getCode(), $codeOnly);
        self::assertStringNotContainsString($automaticTitle, $codeOnly);

        $this->assertPageRenders('/admin/coupon?mode=all&order=id&direction=desc');
        $both = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($automaticTitle, $both);
        self::assertStringContainsString((string) $withCode->getCode(), $both);
    }

    /**
     * The mode filter is a GET form of its own: without the sort carried over,
     * submitting it would silently send the merchant back to the default order.
     */
    public function testTheModeFilterKeepsTheColumnTheListIsSortedOn(): void
    {
        $this->loginAdmin();

        $this->assertPageRenders('/admin/coupon?order=title&direction=desc');
        $crawler = $this->client->getCrawler();

        self::assertSame(
            'title',
            $crawler->filter('form[data-testid="coupons-filter-form"] input[name="order"]')->attr('value'),
        );
        self::assertSame(
            'desc',
            $crawler->filter('form[data-testid="coupons-filter-form"] input[name="direction"]')->attr('value'),
        );
    }

    /**
     * A refused save must give the whole form back as it was typed: the type,
     * its fragment, the descriptions, the dates and the boxes ticked or not.
     * Anything read from the database here would overwrite the merchant's work.
     */
    public function testARefusedSaveComesBackWithEverythingThatWasTyped(): void
    {
        $this->loginAdmin();

        $before = (int) CouponQuery::create()->count();
        $token = $this->couponFormToken('/admin/coupon/create');
        $title = 'Coupon missing its code '.uniqid();

        $crawler = $this->client->request('POST', '/admin/coupon/create', [
            '_token' => $token,
            'locale' => 'en_US',
            'type' => self::COUPON_TYPE,
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => '',
            'title' => $title,
            'shortDescription' => 'Shown in the cart',
            'description' => 'The long story',
            'isEnabled' => '1',
            'isAvailableOnSpecialOffers' => '1',
            'startDate' => '2030-01-02 03:04:05',
            'expirationDate' => '',
            'maxUsage' => '5',
            'perCustomerUsageCount' => '0',
            'coupon_specific' => ['amount' => '12'],
            'save_mode' => 'close',
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($before, (int) CouponQuery::create()->count(), 'Nothing must have been persisted.');

        self::assertSame(
            self::COUPON_TYPE,
            $crawler->filter('select[name="type"] option[selected]')->attr('value'),
            'The refused form must come back on the coupon type that was posted.',
        );
        self::assertSame(
            '12',
            $crawler->filter('#coupon-amount')->attr('value'),
            'The fragment of the posted type must be re-rendered with the posted amount.',
        );

        self::assertSame($title, $crawler->filter('#coupon-title')->attr('value'));
        self::assertSame('Shown in the cart', $crawler->filter('#short-description')->text());
        self::assertSame('The long story', $crawler->filter('#long-description')->text());
        self::assertSame('2030-01-02 03:04:05', $crawler->filter('#start-date')->attr('value'));

        self::assertSame(
            '5',
            $crawler->filter('#max-usage')->attr('value'),
            'A usage count that was typed must survive the refusal.',
        );
        self::assertSame(
            0,
            $crawler->filter('#is-unlimited[checked]')->count(),
            'The unlimited box was not ticked: it must not come back ticked.',
        );

        self::assertGreaterThan(0, $crawler->filter('#is-enabled[checked]')->count());
        self::assertGreaterThan(0, $crawler->filter('#is-available-on-special-offers[checked]')->count());
        self::assertSame(
            0,
            $crawler->filter('#is-cumulative[checked]')->count(),
            'A box left unticked posts nothing at all, and must not come back ticked.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-testid="coupon-trigger-mode-code"][checked]')->count(),
            'The posted trigger mode must come back selected.',
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('#perCustomerUsageCount-0[checked]')->count(),
            'The posted usage scope must come back selected.',
        );
    }
}
