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

namespace Thelia\Tests\Integration\Company;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Company\Checkout\Service\CompanySpendingJudge;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\DTO\CompanyMembership;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * What a buyer reads when the company refuses the order: the sentence of the shop language,
 * and the amounts with the symbol of the currency they are written in.
 */
final class RefusalMessagesTest extends IntegrationTestCase
{
    private ?Lang $lang = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lang = $this->session()->getLang(false);
    }

    protected function tearDown(): void
    {
        if ($this->lang instanceof Lang) {
            $this->session()->setLang($this->lang);
        }

        parent::tearDown();
    }

    public function testASuspendedCompanyIsSaidInFrench(): void
    {
        $this->speak('fr_FR');

        $refusals = $this->judge()->refusalsFor($this->membership(active: false), '1.000000', static fn (): string => '0');

        self::assertSame("Le compte de votre société est suspendu : aucune commande ne peut être passée tant qu'il n'est pas réactivé.", $refusals[0]->getMessage());
    }

    public function testACompanyWithoutBillingAddressIsSaidInFrenchAndInEnglish(): void
    {
        $this->speak('fr_FR');
        $refusals = $this->judge()->refusalsFor($this->membership(billingAddress: false), '1.000000', static fn (): string => '0');
        self::assertSame("Votre société n'a pas encore d'adresse de facturation : vous pourrez commander dès qu'elle sera choisie.", $refusals[0]->getMessage());

        $this->speak('en_US');
        $refusals = $this->judge()->refusalsFor($this->membership(billingAddress: false), '1.000000', static fn (): string => '0');
        self::assertSame('Your company has no billing address yet: orders can be placed once it is chosen.', $refusals[0]->getMessage());
    }

    public function testARoleWithoutThePermissionIsSaidInFrench(): void
    {
        $this->speak('fr_FR');

        $refusals = $this->judge()->refusalsFor($this->membership(permissions: []), '1.000000', static fn (): string => '0');

        self::assertSame('Votre rôle dans votre société ne vous permet pas de passer commande.', $refusals[0]->getMessage());
    }

    public function testTheLimitOfAnOrderIsSaidInFrenchAsTheShopWritesAPrice(): void
    {
        $this->speak('fr_FR');

        $refusals = $this->judge()->refusalsFor($this->membership(orderLimit: '500.000000'), '600.000000', static fn (): string => '0');

        self::assertSame('Cette commande de 600,00 € dépasse les 500,00 € que votre société autorise pour une seule commande.', $this->withoutSpacingVariants($refusals[0]->getMessage()));
        self::assertSame(['limit' => '500', 'total' => '600'], $refusals[0]->violationDetails(), 'The answer of the API keeps plain numbers.');
    }

    public function testTheLimitOverAPeriodIsSaidInFrenchAsTheShopWritesAPrice(): void
    {
        $this->speak('fr_FR');

        $refusals = $this->judge()->refusalsFor(
            $this->membership(periodLimit: '2000.000000', period: 'month'),
            '300.000000',
            static fn (): string => '1800.000000',
        );

        self::assertSame(
            'Cette commande de 300,00 €, ajoutée aux 1 800,00 € déjà commandés sur la période, dépasse les 2 000,00 € que votre société autorise.',
            $this->withoutSpacingVariants($refusals[0]->getMessage()),
        );
    }

    public function testTheMessageIsSaidInEnglishAsTheShopWritesAPriceWithTheSymbolBeforeTheAmount(): void
    {
        $this->speak('en_US');

        $refusals = $this->judge()->refusalsFor($this->membership(orderLimit: '500.000000'), '600.000000', static fn (): string => '0');

        self::assertSame('This order of €600.00 exceeds the €500.00 your company allows for a single order.', $this->withoutSpacingVariants($refusals[0]->getMessage()));
    }

    private function speak(string $locale): void
    {
        $lang = LangQuery::create()->findOneByLocale($locale);
        self::assertNotNull($lang);

        $this->session()->setLang($lang);
    }

    private function session(): Session
    {
        $session = static::getContainer()->get('request_stack')->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    private function judge(): CompanySpendingJudge
    {
        return new CompanySpendingJudge($this->getService(FormatService::class));
    }

    private function withoutSpacingVariants(string $message): string
    {
        return preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $message) ?? $message;
    }

    /**
     * @param list<string>|null $permissions
     */
    private function membership(bool $active = true, ?string $orderLimit = null, ?string $periodLimit = null, ?string $period = null, ?array $permissions = null, bool $billingAddress = true): CompanyMembership
    {
        return new CompanyMembership(
            companyId: 3,
            customerId: 12,
            roleCode: 'buyer',
            permissions: $permissions ?? [CompanyPermission::ORDER_PLACE],
            companyActive: $active,
            companyHasBillingAddress: $billingAddress,
            orderLimit: $orderLimit,
            periodLimit: $periodLimit,
            period: $period,
        );
    }
}
