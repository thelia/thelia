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

use BackOfficeDefaultTwigBundle\Service\Coupon\CouponStackingMode;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\Coupon;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Model\CouponQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The stacking block of the promotion editor: one choice (combines, stops the next
 * ones, applies alone), a priority and the promotions it is incompatible with.
 */
final class CouponStackingFieldsTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // The core ships with whichever back-office theme it is given: one that
        // predates the stacking block has nothing to assert on.
        if (!class_exists(CouponStackingMode::class)) {
            self::markTestSkipped('The installed back-office theme predates the stacking of promotions.');
        }

        parent::setUp();

        $template = static::getContainer()->getParameter('thelia_admin_template');

        if ('default-twig' !== $template) {
            self::markTestSkipped(\sprintf(
                'The installed back-office template is "%s", not "default-twig".',
                \is_string($template) ? $template : 'unknown',
            ));
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    public function testEachChoiceAndThePriorityComeBackAsSaved(): void
    {
        $this->loginAdmin();
        $coupon = $this->factory()->coupon(['code' => 'STK-'.strtoupper(uniqid())]);
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        foreach ([
            ['cumulative', true, false, 3],
            ['stop', true, true, -2],
            ['alone', false, true, 0],
        ] as [$mode, $cumulative, $stop, $priority]) {
            $this->save($coupon, $token, ['stackingMode' => $mode, 'priority' => (string) $priority]);
            self::assertSame(302, $this->client->getResponse()->getStatusCode(), $mode);

            $stored = CouponQuery::create()->findPk($coupon->getId());
            self::assertSame($cumulative, (bool) $stored->getIsCumulative(), $mode.' cumulative');
            self::assertSame($stop, (bool) $stored->getStopProcessing(), $mode.' stop');
            self::assertSame($priority, (int) $stored->getPriority(), $mode.' priority');

            $this->assertPageRenders('/admin/coupon/update/'.$coupon->getId());
            self::assertSame(
                $mode,
                $this->client->getCrawler()->filter('input[name="stackingMode"][checked]')->attr('value'),
                'The editor must reopen on the stored choice.',
            );
            self::assertSame((string) $priority, $this->client->getCrawler()->filter('input[name="priority"]')->attr('value'));
        }
    }

    public function testAPromotionThatDoesNotCombineOpensAsApplyingAlone(): void
    {
        $this->loginAdmin();
        // Whatever the stop flag says, a promotion that does not combine applies alone.
        $coupon = $this->factory()->coupon(['isCumulative' => false, 'stopProcessing' => false]);

        $this->assertPageRenders('/admin/coupon/update/'.$coupon->getId());

        self::assertSame('alone', $this->client->getCrawler()->filter('input[name="stackingMode"][checked]')->attr('value'));
    }

    public function testExclusionsAreSymmetricAndReadFromTheOppositePromotion(): void
    {
        $this->loginAdmin();
        $factory = $this->factory();
        $first = $factory->coupon(['code' => 'EXA-'.strtoupper(uniqid())]);
        $second = $factory->coupon(['code' => 'EXB-'.strtoupper(uniqid())]);
        $third = $factory->coupon(['code' => 'EXC-'.strtoupper(uniqid())]);
        $token = $this->token('/admin/coupon/update/'.$first->getId());

        $this->save($first, $token, ['stackingMode' => 'cumulative', 'excludedCouponIds' => [(string) $second->getId(), (string) $third->getId()]]);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        self::assertSame([$first->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($second->getId()));

        $this->assertPageRenders('/admin/coupon/update/'.$second->getId());
        $selected = $this->client->getCrawler()->filter('select[name="excludedCouponIds[]"] option[selected]');
        self::assertSame([(string) $first->getId()], $selected->each(static fn ($option): string => (string) $option->attr('value')));
        $options = $this->client->getCrawler()->filter('select[name="excludedCouponIds[]"] option')->each(static fn ($option): string => (string) $option->attr('value'));
        self::assertNotContains((string) $second->getId(), $options, 'A promotion is never offered as incompatible with itself.');

        // Clearing the list on the first one clears both directions.
        $this->save($first, $token, ['stackingMode' => 'cumulative']);
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($second->getId()));
    }

    public function testAnExistingPromotionIsSavedAgainUnchangedWithoutTypingAnything(): void
    {
        $this->loginAdmin();
        $factory = $this->factory();
        $other = $factory->coupon();
        $coupon = $factory->coupon(['isCumulative' => true, 'stopProcessing' => true, 'priority' => 7, 'code' => 'KEEP-'.strtoupper(uniqid())]);
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());
        $this->save($coupon, $token, ['stackingMode' => 'stop', 'priority' => '7', 'excludedCouponIds' => [(string) $other->getId()]]);

        $this->assertPageRenders('/admin/coupon/update/'.$coupon->getId());
        $form = $this->client->getCrawler()->filter('form[data-testid="coupon-edit-form"]')->form();
        $this->client->request('POST', '/admin/coupon/update/'.$coupon->getId(), $form->getPhpValues());
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $stored = CouponQuery::create()->findPk($coupon->getId());
        self::assertTrue((bool) $stored->getIsCumulative());
        self::assertTrue((bool) $stored->getStopProcessing());
        self::assertSame(7, (int) $stored->getPriority());
        self::assertSame([$other->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));
    }

    public function testExcludingAPromotionFromItselfIsRefusedWithoutAServerError(): void
    {
        $this->loginAdmin();
        $factory = $this->factory();
        $coupon = $factory->coupon(['priority' => 4]);
        $other = $factory->coupon();
        // A pair that exists before the refusal: it must still be there after.
        $this->getService('Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter')->replace($coupon->getId(), [$other->getId()]);
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        $this->save($coupon, $token, ['stackingMode' => 'alone', 'priority' => '9', 'excludedCouponIds' => [(string) $coupon->getId()]]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $alert = $this->client->getCrawler()->filter('[role="alert"], .alert-danger');
        self::assertGreaterThan(0, $alert->count(), 'The refusal must be shown on the screen.');
        self::assertStringContainsString(
            'The incompatible promotions are not valid',
            $alert->text(),
            'The refusal is the back office text of the screen, not the words of the core.',
        );
        self::assertStringNotContainsString('cannot exclude itself', $alert->text());
        self::assertSame('alone', $this->client->getCrawler()->filter('input[name="stackingMode"][checked]')->attr('value'), 'What was typed comes back.');
        self::assertSame('9', $this->client->getCrawler()->filter('input[name="priority"]')->attr('value'));
        // That a refused exclusion also rolls the promotion back is proven by CouponActionAtomicityTest:
        // the test transaction here swallows the rollback of the nested one.
        self::assertSame([$other->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()), 'The pair that existed survives the refusal.');
    }

    public function testALoneIdentifierPostedInsteadOfAListIsReadAsAListOfOne(): void
    {
        $this->loginAdmin();
        $factory = $this->factory();
        $coupon = $factory->coupon();
        $other = $factory->coupon();
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        $this->save($coupon, $token, ['stackingMode' => 'cumulative', 'excludedCouponIds' => (string) $other->getId()]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame([$other->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()), 'A scalar must not read as an empty list that clears everything.');
    }

    public function testAPriorityThatIsNotANumberIsRefusedAndComesBackAsTyped(): void
    {
        $this->loginAdmin();
        $coupon = $this->factory()->coupon(['priority' => 4, 'isCumulative' => true, 'stopProcessing' => false]);
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        $this->save($coupon, $token, ['stackingMode' => 'stop', 'priority' => 'abc']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertGreaterThan(0, $this->client->getCrawler()->filter('[role="alert"], .alert-danger')->count());
        self::assertSame('abc', $this->client->getCrawler()->filter('input[name="priority"]')->attr('value'));
        self::assertSame('stop', $this->client->getCrawler()->filter('input[name="stackingMode"][checked]')->attr('value'));
        self::assertSame(4, (int) CouponQuery::create()->findPk($coupon->getId())->getPriority());
    }

    public function testAPriorityBeyondTheColumnIsRefusedWithoutAServerError(): void
    {
        $this->loginAdmin();
        $coupon = $this->factory()->coupon(['priority' => 4, 'isCumulative' => true, 'stopProcessing' => false]);
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        $this->save($coupon, $token, ['stackingMode' => 'cumulative', 'priority' => '9999999999']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertGreaterThan(0, $this->client->getCrawler()->filter('[role="alert"], .alert-danger')->count());
        self::assertSame(4, (int) CouponQuery::create()->findPk($coupon->getId())->getPriority());
    }

    public function testAnUnknownPromotionIsRefusedWithoutAServerError(): void
    {
        $this->loginAdmin();
        $coupon = $this->factory()->coupon();
        $token = $this->token('/admin/coupon/update/'.$coupon->getId());

        $this->save($coupon, $token, ['stackingMode' => 'cumulative', 'excludedCouponIds' => ['999999999']]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));
    }

    public function testAnExclusionChosenAtCreationIsRecordedOnBothSides(): void
    {
        $this->loginAdmin();
        $other = $this->factory()->coupon();
        $token = $this->token('/admin/coupon/create');
        $code = 'NEW-'.strtoupper(uniqid());

        $this->client->request('POST', '/admin/coupon/create', $this->payload($token, [
            'code' => $code,
            'title' => 'New '.$code,
            'stackingMode' => 'stop',
            'priority' => '5',
            'excludedCouponIds' => [(string) $other->getId()],
        ]));
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $created = CouponQuery::create()->filterByCode($code)->findOne();
        self::assertNotNull($created);
        self::assertSame(5, (int) $created->getPriority());
        self::assertTrue((bool) $created->getStopProcessing());
        self::assertSame([$created->getId()], CouponExclusionQuery::create()->excludedCouponIdsFor($other->getId()));
    }

    public function testAnAdminWithoutTheRightCannotReadOrWrite(): void
    {
        $factory = $this->factory();
        $coupon = $factory->coupon(['priority' => 4]);
        $other = $factory->coupon();
        $this->loginAs($factory->restrictedAdmin(['admin.coupon' => [AccessManager::VIEW]]));

        $token = $this->token('/admin/coupon/update/'.$coupon->getId());
        $this->client->request('POST', '/admin/coupon/update/'.$coupon->getId(), $this->payload($token, [
            'code' => (string) $coupon->getCode(),
            'title' => 'Hacked',
            'priority' => '99',
            'excludedCouponIds' => [(string) $other->getId()],
        ]));
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'A reader cannot save.');
        self::assertSame(4, (int) CouponQuery::create()->findPk($coupon->getId())->getPriority());
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($coupon->getId()));

        $this->loginAs($factory->restrictedAdmin(['admin.configuration' => [AccessManager::VIEW]]));
        $this->client->catchExceptions(false);
        foreach (['/admin/coupon', '/admin/coupon/update/'.$coupon->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertSame(403, $this->client->getResponse()->getStatusCode(), $url);
            self::assertStringNotContainsString((string) $other->getCode(), (string) $this->client->getResponse()->getContent(), 'No promotion data is served to a refused admin.');
        }
    }

    public function testTheListSortsOnThePriorityAndShowsTheStacking(): void
    {
        $this->loginAdmin();
        $factory = $this->factory();
        $tag = strtoupper(uniqid());
        $low = $factory->coupon(['code' => 'LOW-'.$tag, 'priority' => -50, 'isCumulative' => true]);
        $high = $factory->coupon(['code' => 'HIGH-'.$tag, 'priority' => 50, 'isCumulative' => false]);
        $other = $factory->coupon(['code' => 'OTH-'.$tag, 'priority' => 0, 'isCumulative' => true]);
        $this->getService('Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter')->replace($low->getId(), [$other->getId()]);

        foreach (['ASC' => [$low, $other, $high], 'DESC' => [$high, $other, $low]] as $direction => $expected) {
            $this->assertPageRenders('/admin/coupon?order=priority&direction='.$direction.'&per_page=1000');
            $codes = array_values(array_filter(
                $this->client->getCrawler()->filter('[data-testid="coupons-page"] table tbody tr')->each(static fn ($row): string => $row->text()),
                static fn (string $text): bool => str_contains($text, $tag),
            ));
            self::assertCount(3, $codes);
            foreach ($expected as $position => $coupon) {
                self::assertStringContainsString((string) $coupon->getCode(), $codes[$position], $direction);
            }
        }

        $text = $this->client->getCrawler()->filter('[data-testid="coupons-page"] table')->text();
        self::assertStringContainsString('Applies alone', $text);
        self::assertStringContainsString('1 incompatible', $text);
    }

    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAdmin(): void
    {
        $this->loginAs($this->factory()->admin());
    }

    private function loginAs(\Thelia\Model\Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    private function token(string $url): string
    {
        $this->client->catchExceptions(true);
        $this->client->request('GET', $url);
        $token = $this->client->getCrawler()->filter('input[name="_token"]');

        return $token->count() > 0 ? (string) $token->first()->attr('value') : 'none';
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function save(Coupon $coupon, string $token, array $overrides): void
    {
        $withMarker = ['excludedCouponIdsPresent' => '1'];
        $this->client->request('POST', '/admin/coupon/update/'.$coupon->getId(), $this->payload($token, $overrides + $withMarker + [
            'code' => (string) $coupon->getCode(),
            'title' => (string) $coupon->getTitle(),
        ]));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(string $token, array $overrides): array
    {
        return array_replace([
            '_token' => $token,
            'locale' => 'en_US',
            'type' => 'thelia.coupon.type.remove_x_amount',
            'trigger_mode' => Coupon::TRIGGER_MODE_CODE,
            'code' => '',
            'title' => 'Stacking coupon',
            'shortDescription' => '',
            'description' => '',
            'isEnabled' => '1',
            'expirationDate' => '',
            'is-unlimited' => '1',
            'perCustomerUsageCount' => '1',
            'coupon_specific' => ['amount' => '5'],
            'excludedCouponIdsPresent' => '1',
            'save_mode' => 'close',
        ], $overrides);
    }
}
