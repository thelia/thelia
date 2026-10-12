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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Legal\Service\VatNumberVerifierInterface;
use Thelia\Domain\Legal\VatVerificationResult;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * What the merchant reads and sets about the intra-community exemption.
 *
 * The screens live in the back-office theme, a separate composer package, so
 * every test guards on the class it needs: a project installed with an older
 * theme skips rather than fails.
 */
final class VatExemptionBackOfficeTest extends WebIntegrationTestCase
{
    private const CUSTOMER_LIST_URL = '/admin/customers';
    private const FILTERS_CLASS = 'BackOfficeDefaultTwigBundle\\Service\\Customer\\CustomerFilters';
    private const REVERIFICATION_LIMIT = 30;

    private const VERIFICATION_CONTROLLER_CLASS = 'BackOfficeDefaultTwigBundle\\Controller\\Customer\\AddressVatVerificationController';

    private ?AdminSessionInjector $injector = null;

    private ?Admin $signedInAdmin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();

        // Memoizes in a static cache that outlives the transaction rollback.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheOrderSheetStatesTheVatThatWasNotCharged(): void
    {
        $this->skipUnlessTheThemeReadsTheExemptedAmount();

        $factory = $this->factory();
        $order = $factory->order();
        $order->getOrderAddressRelatedByInvoiceOrderAddressId()
            ->setVatNumber('BE0123456789')
            ->setVatExempted(1)
            ->setVatExemptedAmount('42.50')
            ->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders('/admin/order/update/'.$order->getId());

        $card = $this->client->getCrawler()
            ->filter('[data-testid="order-invoice-address-vat-exempted-amount"]')
            ->text();

        self::assertStringContainsString('42.50', $card, 'The amount is read from the order address, not recomputed.');
    }

    public function testTheAddressSheetShowsTheVerificationState(): void
    {
        $this->skipUnlessTheThemeShowsTheVerificationState();

        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $address = $factory->address($customer);
        $address
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-2 days'))
            ->setVatVerifiedName('ACME SPRL')
            ->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders('/admin/address/update?address_id='.$address->getId());

        $block = $this->client->getCrawler()->filter('[data-testid="address-vat-verification"]');

        self::assertGreaterThan(0, $block->count(), 'A verified address states when it was verified.');
        self::assertStringContainsString('ACME SPRL', $block->text());
    }

    /**
     * A verification older than the lifetime no longer exempts, and the sheet
     * has to say so rather than show the same green line as a fresh one.
     */
    public function testAnExpiredVerificationIsShownAsNoLongerExempting(): void
    {
        $this->skipUnlessTheThemeShowsTheVerificationState();

        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $address = $factory->address($customer);
        $address
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-'.(ConfigQuery::getVatVerificationLifetimeDays() + 10).' days'))
            ->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders('/admin/address/update?address_id='.$address->getId());

        $block = $this->client->getCrawler()->filter('[data-testid="address-vat-verification"]');

        self::assertGreaterThan(0, $block->count());
        self::assertStringNotContainsString(
            'alert-success',
            (string) $block->html(),
            'An expired verification must not read like a valid one.',
        );
    }

    public function testTheCustomerListFilterKeepsOnlyTheCustomersWithAVerifiedAddress(): void
    {
        $this->skipUnlessTheThemeFiltersOnVerification();

        $factory = $this->factory();
        $verified = $factory->customer($factory->customerTitle(), ['lastname' => 'VatVerifiedOne']);
        $factory->address($verified)
            ->setVatVerifiedAt(new \DateTime('-2 days'))
            ->save($this->getPropelConnection());
        $plain = $factory->customer($factory->customerTitle(), ['lastname' => 'VatUnverifiedOne']);
        $factory->address($plain);

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::CUSTOMER_LIST_URL.'?vat_verified=with');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString((string) $verified->getRef(), $html);
        self::assertStringNotContainsString((string) $plain->getRef(), $html, 'An unverified customer is filtered out.');
    }

    /**
     * The complement is not "everyone else": a verification that has aged out
     * puts its customer back among those with nothing verified.
     */
    public function testAnExpiredVerificationFallsBackToTheWithoutSide(): void
    {
        $this->skipUnlessTheThemeFiltersOnVerification();

        $factory = $this->factory();
        $stale = $factory->customer($factory->customerTitle(), ['lastname' => 'VatStaleOne']);
        $factory->address($stale)
            ->setVatVerifiedAt(new \DateTime('-'.(ConfigQuery::getVatVerificationLifetimeDays() + 10).' days'))
            ->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::CUSTOMER_LIST_URL.'?vat_verified=with');
        self::assertStringNotContainsString(
            (string) $stale->getRef(),
            (string) $this->client->getResponse()->getContent(),
            'An aged-out verification does not exempt, so it does not answer "verified" either.',
        );

        $this->assertPageRenders(self::CUSTOMER_LIST_URL.'?vat_verified=without');
        self::assertStringContainsString(
            (string) $stale->getRef(),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testTheStoreConfigurationRecordsTheExemptionMode(): void
    {
        $this->skipUnlessTheThemeOffersTheSetting();

        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders('/admin/configuration/store');

        $button = $this->client->getCrawler()->filter('[data-testid="config-store-save-stay"]');
        self::assertGreaterThan(0, $button->count(), 'The store configuration form must expose its Save button.');

        // The test database seeds no store configuration, so the required
        // fields of the form are blank on the rendered page: a submission that
        // leaves them out is rejected on them, not on the one field under test.
        $this->client->submit($button->form([
            'thelia_configuration_store[store_name]' => 'Test Store',
            'thelia_configuration_store[store_email]' => 'store@test.com',
            'thelia_configuration_store[store_notification_emails]' => 'store@test.com',
            'thelia_configuration_store[store_address1]' => '1 Main Street',
            'thelia_configuration_store[store_zipcode]' => '75001',
            'thelia_configuration_store[store_city]' => 'Paris',
            // store_vat_exempt is a checkbox and stays unticked: the coherence
            // rule refuses an exemption on a shop that charges no VAT at all.
            'thelia_configuration_store[vat_exemption_mode]' => VatExemptionMode::VERIFIED_VAT_NUMBER->value,
        ]));

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'A valid store configuration redirects: any 200 here means the form was rejected.',
        );

        ConfigQuery::resetCache();

        self::assertSame(
            VatExemptionMode::VERIFIED_VAT_NUMBER->value,
            ConfigQuery::read(VatExemptionMode::CONFIG_KEY),
            'The setting is read back from the config table, not from the form.',
        );
    }

    /**
     * Deliberately not createFixtureFactory(): that helper pushes a synthetic
     * request when the stack is empty, which would then be the "main" request
     * the security context reads its session from.
     */
    public function testAnUnansweredReverificationKeepsTheVerificationItReportsAsUnchanged(): void
    {
        $this->skipUnlessTheReverificationSparesAnUnansweredCheck();

        $this->installVerifierAnswering(VatVerificationResult::undetermined());
        $address = $this->verifiedAddressOnItsSheet();

        $this->postReverification($address, $this->reverificationToken());

        $reloaded = $this->reloaded($address);
        self::assertNotNull($reloaded->getVatVerifiedAt());
        self::assertSame('ACME SPRL', $reloaded->getVatVerifiedName());
    }

    public function testAVerifierThatFailsLeavesTheVerificationAsItWasInsteadOfAnErrorPage(): void
    {
        $this->skipUnlessTheReverificationAnswersAVerifierThatThrows();

        $this->installVerifierAnswering(new \RuntimeException('The verifier is broken.'));
        $address = $this->verifiedAddressOnItsSheet();

        $this->postReverification($address, $this->reverificationToken());

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('ACME SPRL', $this->reloaded($address)->getVatVerifiedName());
    }

    public function testAReverificationWithoutItsTokenNeverReachesTheVerifier(): void
    {
        $this->skipUnlessTheThemeShowsTheVerificationState();

        $verifier = $this->installVerifierAnswering(VatVerificationResult::refused(new \DateTimeImmutable()));
        $address = $this->verifiedAddressOnItsSheet();

        $this->postReverification($address, 'forged-token');

        self::assertSame(0, $verifier->calls);
        self::assertSame('ACME SPRL', $this->reloaded($address)->getVatVerifiedName());
    }

    public function testReverificationsBeyondTheLimitNeverReachTheVerifier(): void
    {
        $this->skipUnlessTheThemeShowsTheVerificationState();

        $verifier = $this->installVerifierAnswering(VatVerificationResult::verified(new \DateTimeImmutable(), 'ACME SPRL'));
        $address = $this->verifiedAddressOnItsSheet();
        $token = $this->reverificationToken();
        $this->getService('limiter.vat_reverification')->create($this->signedInAdmin?->getUsername())->reset();

        for ($attempt = 0; $attempt < self::REVERIFICATION_LIMIT + 5; ++$attempt) {
            $this->postReverification($address, $token);
        }

        self::assertSame(self::REVERIFICATION_LIMIT, $verifier->calls);
    }

    public function testAStoreOutOfTheVatScopeCannotExemptItsBuyers(): void
    {
        $this->skipUnlessTheThemeOffersTheSetting();

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);
        ConfigQuery::write('store_vat_exempt', '0');

        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders('/admin/configuration/store');
        $button = $this->client->getCrawler()->filter('[data-testid="config-store-save-stay"]');
        self::assertGreaterThan(0, $button->count(), 'The store configuration form must expose its Save button.');

        $form = $button->form([
            'thelia_configuration_store[store_name]' => 'Test Store',
            'thelia_configuration_store[store_email]' => 'store@test.com',
            'thelia_configuration_store[store_notification_emails]' => 'store@test.com',
            'thelia_configuration_store[store_address1]' => '1 Main Street',
            'thelia_configuration_store[store_zipcode]' => '75001',
            'thelia_configuration_store[store_city]' => 'Paris',
            'thelia_configuration_store[vat_exemption_mode]' => VatExemptionMode::VERIFIED_VAT_NUMBER->value,
        ]);
        $form['thelia_configuration_store[store_vat_exempt]']->tick();
        $this->client->submit($form);

        self::assertNotSame(302, $this->client->getResponse()->getStatusCode(), 'A store out of the VAT scope must not save an exemption of its buyers.');
        self::assertStringContainsString('A store that charges no VAT has none to reverse onto its buyers.', (string) $this->client->getResponse()->getContent());

        self::assertSame('0', ConfigQuery::create()->findOneByName('store_vat_exempt')?->getValue());
        self::assertSame(VatExemptionMode::DISABLED->value, ConfigQuery::create()->findOneByName(VatExemptionMode::CONFIG_KEY)?->getValue());
    }

    private function installVerifierAnswering(VatVerificationResult|\Throwable $answer): object
    {
        $verifier = new class($answer) implements VatNumberVerifierInterface {
            public int $calls = 0;

            public function __construct(private readonly VatVerificationResult|\Throwable $answer)
            {
            }

            public function verify(string $vatNumber, string $countryIsoAlpha2): VatVerificationResult
            {
                ++$this->calls;

                if ($this->answer instanceof \Throwable) {
                    throw $this->answer;
                }

                return $this->answer;
            }
        };
        static::getContainer()->set(VatNumberVerifierInterface::class, $verifier);

        return $verifier;
    }

    private function verifiedAddressOnItsSheet(): Address
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $address = $factory->address($customer);
        $address
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt(new \DateTime('-2 days'))
            ->setVatVerifiedName('ACME SPRL')
            ->save($this->getPropelConnection());

        $this->signedInAdmin = $factory->admin();
        $this->loginAs($this->signedInAdmin);
        $this->assertPageRenders('/admin/address/update?address_id='.$address->getId());

        return $address;
    }

    private function reverificationToken(): string
    {
        $token = $this->client->getCrawler()->filter('form[data-testid="address-vat-verify-form"] input[name="_token"]');
        self::assertGreaterThan(0, $token->count(), 'The address sheet offers the re-verification once a verifier is installed.');

        return (string) $token->attr('value');
    }

    private function postReverification(Address $address, string $token): void
    {
        $this->client->request('POST', '/admin/address/vat/verify', [
            'address_id' => (string) $address->getId(),
            '_token' => $token,
        ]);
    }

    private function reloaded(Address $address): Address
    {
        AddressTableMap::clearInstancePool();
        $reloaded = AddressQuery::create()->findPk($address->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    private function skipUnlessTheReverificationAnswersAVerifierThatThrows(): void
    {
        if (!class_exists(self::VERIFICATION_CONTROLLER_CLASS)) {
            self::markTestSkipped('The installed back-office theme offers no re-verification.');
        }

        $source = (string) file_get_contents((string) (new \ReflectionClass(self::VERIFICATION_CONTROLLER_CLASS))->getFileName());

        if (!str_contains($source, 'VatVerificationResult::undetermined()')) {
            self::markTestSkipped('The installed back-office theme lets a failing verifier through as an error page.');
        }
    }

    private function skipUnlessTheReverificationSparesAnUnansweredCheck(): void
    {
        if (!class_exists(self::VERIFICATION_CONTROLLER_CLASS)) {
            self::markTestSkipped('The installed back-office theme offers no re-verification.');
        }

        $source = (string) file_get_contents((string) (new \ReflectionClass(self::VERIFICATION_CONTROLLER_CLASS))->getFileName());

        if (!str_contains($source, 'VatVerificationStatus::UNDETERMINED !== $result->status')) {
            self::markTestSkipped('The installed back-office theme still records an unanswered re-verification.');
        }
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

    private function skipUnlessTheThemeReadsTheExemptedAmount(): void
    {
        $controller = 'BackOfficeDefaultTwigBundle\\Controller\\Order\\OrderController';

        if (!class_exists($controller)) {
            self::markTestSkipped('The installed back-office theme has no order controller.');
        }

        if (!str_contains((string) file_get_contents((string) (new \ReflectionClass($controller))->getFileName()), 'vat_exempted_amount')) {
            self::markTestSkipped('The installed back-office theme does not read the exempted amount yet.');
        }
    }

    private function skipUnlessTheThemeShowsTheVerificationState(): void
    {
        if (!class_exists(self::VERIFICATION_CONTROLLER_CLASS)) {
            self::markTestSkipped('The installed back-office theme does not show the VAT verification state yet.');
        }
    }

    private function skipUnlessTheThemeFiltersOnVerification(): void
    {
        if (!class_exists(self::FILTERS_CLASS) || !property_exists(self::FILTERS_CLASS, 'vatVerified')) {
            self::markTestSkipped('The installed back-office theme has no VAT verification filter.');
        }
    }

    private function skipUnlessTheThemeOffersTheSetting(): void
    {
        $type = 'BackOfficeDefaultTwigBundle\\Form\\Configuration\\ConfigStoreType';

        if (!class_exists($type)) {
            self::markTestSkipped('The installed back-office theme has no store configuration form.');
        }

        if (!str_contains((string) file_get_contents((string) (new \ReflectionClass($type))->getFileName()), VatExemptionMode::CONFIG_KEY)) {
            self::markTestSkipped('The installed back-office theme does not offer the exemption setting yet.');
        }
    }
}
