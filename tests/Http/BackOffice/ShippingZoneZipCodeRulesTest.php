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

use BackOfficeDefaultTwigBundle\Controller\Configuration\AreaZipCodeRuleController;
use Symfony\Component\DomCrawler\Form;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Area;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\AreaZipCodeRuleQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\LangQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The postal code card of a shipping zone: the rules it lists, adding and deleting one,
 * and the refusals the core makes readable.
 */
final class ShippingZoneZipCodeRulesTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        if (!class_exists(AreaZipCodeRuleController::class)) {
            self::markTestSkipped('The installed back-office theme has no postal code rules on shipping zones.');
        }

        parent::setUp();

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

    public function testTheZonePageListsItsRules(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders($this->zoneUrl($area));

        $row = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-'.$rule->getId().'"]');
        self::assertSame(1, $row->count());
        self::assertStringContainsString('20*', $row->text());
    }

    public function testARuleForACountryThatLeftTheZoneDoesNotReadAsEveryCountry(): void
    {
        $area = $this->area();
        $foreign = $this->factory()->country(['isoalpha2' => 'XU', 'isoalpha3' => 'XUX', 'isocode' => '994']);
        $rule = $this->rule($area, '20*', true);
        $rule->setCountryId($foreign->getId())->save($this->getPropelConnection());
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders($this->zoneUrl($area));

        $row = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-'.$rule->getId().'"]');
        self::assertStringContainsString('A country that left the zone', $row->text());
        self::assertStringNotContainsString('Every country of the zone', $row->text());
    }

    /**
     * Where the address forms check no postal code format, a customer types any code, or none,
     * and gets past the exclusions: the card says so for the countries its rules apply to.
     */
    public function testTheCardWarnsAboutTheCountriesWhosePostalCodesAreNotChecked(): void
    {
        $area = $this->area();
        $checked = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $unformatted = $this->countryOfTheZone($area, 'XW', '996', '', true);
        $notRequired = $this->countryOfTheZone($area, 'XY', '997', 'NNNNN', false);
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders($this->zoneUrl($area));
        $warning = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rules-unformatted"]');
        self::assertSame(1, $warning->count(), 'A rule for every country of the zone covers the two countries.');
        self::assertStringContainsString($unformatted->getTitle(), $warning->text());
        self::assertStringContainsString($notRequired->getTitle(), $warning->text());
        self::assertStringNotContainsString($checked->getTitle(), $warning->text());

        $rule->setCountryId($checked->getId())->save($this->getPropelConnection());

        $this->assertPageRenders($this->zoneUrl($area));
        self::assertSame(0, $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rules-unformatted"]')->count(), 'The only rule applies to a country whose codes are checked.');
    }

    public function testARuleAddedFromTheCardIsSavedInItsNormalForm(): void
    {
        $area = $this->area();
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));

        $form = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $this->client->submit($form, ['pattern' => '75 001...75 020', 'excluded' => '0', 'country_id' => '0']);

        self::assertTrue($this->client->getResponse()->isRedirect());
        $rule = AreaZipCodeRuleQuery::create()->findOneByAreaId($area->getId());
        self::assertNotNull($rule);
        self::assertSame('75001...75020', $rule->getPattern());
        self::assertFalse($rule->getExcluded());
    }

    public function testAPatternThatMatchesNothingIsRefusedWithItsReason(): void
    {
        $area = $this->area();
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));

        $form = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $this->client->submit($form, ['pattern' => '75020...75001', 'excluded' => '1']);
        $this->client->followRedirect();

        self::assertSame(0, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count());
        self::assertStringContainsString('75001...75020', $this->client->getCrawler()->filter('[data-testid="bo-flash-danger"]')->text());
    }

    public function testDeletingARuleRemovesIt(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));

        $this->client->submit($this->deleteForm($rule));

        self::assertNull(AreaZipCodeRuleQuery::create()->findPk($rule->getId()));
    }

    public function testDeletingARuleAsksForAConfirmationFirst(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $crawler = $this->client->getCrawler();

        $button = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-delete-'.$rule->getId().'"]');
        self::assertSame('button', $button->attr('type'), 'One click on the row deletes nothing.');
        self::assertSame('#shipping-configuration-zip-code-rule-delete-modal', $button->attr('data-bs-target'));
        self::assertSame(0, $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-'.$rule->getId().'"] form')->count());
        self::assertStringContainsString(
            'Do you really want to delete this postal code rule?',
            $crawler->filter('#shipping-configuration-zip-code-rule-delete-modal')->text(),
        );
    }

    public function testAddingAndDeletingARuleIsWrittenToTheAdministrationLog(): void
    {
        $area = $this->area();
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));

        $form = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $this->client->submit($form, ['pattern' => '20 *', 'excluded' => '1', 'country_id' => '0']);
        $rule = AreaZipCodeRuleQuery::create()->findOneByAreaId($area->getId());
        self::assertNotNull($rule);

        self::assertSame(1, $this->logEntries($area, 'Postal code rule "20*" (ID '.$rule->getId().') added to shipping zone '.$area->getId()));

        $this->assertPageRenders($this->zoneUrl($area));
        $this->client->submit($this->deleteForm($rule));

        self::assertSame(1, $this->logEntries($area, 'Postal code rule '.$rule->getId().' deleted from shipping zone '.$area->getId()));
    }

    /**
     * The table is masked for the connection of the test (a temporary table of the same name,
     * no implicit commit): the save fails as a database failure would.
     */
    public function testADatabaseFailureIsToldInAPlainSentence(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '75*', false);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $crawler = $this->client->getCrawler();
        $add = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $delete = $this->deleteForm($rule);
        $connection = $this->getPropelConnection();

        foreach ([[$add, ['pattern' => '20*', 'excluded' => '1']], [$delete, []]] as [$form, $values]) {
            $connection->exec('CREATE TEMPORARY TABLE `area_zip_code_rule` (id INT)');

            try {
                $this->client->submit($form, $values);
            } finally {
                $connection->exec('DROP TEMPORARY TABLE `area_zip_code_rule`');
            }

            self::assertTrue($this->client->getResponse()->isRedirect(), 'A failure of the database is no error page.');
            $this->client->followRedirect();
            $flash = $this->client->getCrawler()->filter('[data-testid="bo-flash-danger"]')->text();
            self::assertMatchesRegularExpression('/The postal code rule could not be (saved|deleted)\. Try again later\./', $flash);
            self::assertStringNotContainsStringIgnoringCase('SQL', $flash);
        }

        self::assertSame(1, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'Nothing was written.');
    }

    public function testAnAdministratorWithoutTheRightToTheZonesChangesNoRule(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $crawler = $this->client->getCrawler();
        $add = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $delete = $this->deleteForm($rule);

        $this->loginAs($this->factory()->restrictedAdmin([AdminResources::AREA => [AccessManager::VIEW]]));

        $this->client->submit($add, ['pattern' => '75*']);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->client->submit($delete);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        self::assertSame(1, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count());
    }

    public function testAPostWithoutTheTokenAddsNothing(): void
    {
        $area = $this->area();
        $this->loginAs($this->factory()->admin());

        $this->client->request('POST', '/admin/configuration/shipping_configuration/'.$area->getId().'/zip-code-rules', ['pattern' => '75*']);

        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertSame(0, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count());
    }

    /**
     * The confirmation dialog of the zone page the client is on, as the delete button of the
     * rule fills it.
     */
    private function deleteForm(AreaZipCodeRule $rule): Form
    {
        $crawler = $this->client->getCrawler();
        $button = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-delete-'.$rule->getId().'"]');
        self::assertSame(1, $button->count(), 'The rule has a delete button.');

        $form = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-delete-form"]')->form();
        $form['rule_id']->setValue((string) $button->attr('data-rule-id'));

        return $form;
    }

    private function logEntries(Area $area, string $message): int
    {
        return AdminLogQuery::create()
            ->filterByResource(AdminResources::AREA)
            ->filterByResourceId($area->getId())
            ->filterByMessage($message)
            ->count();
    }

    private function countryOfTheZone(Area $area, string $alpha2, string $isoCode, string $zipCodeFormat, bool $needZipCode): Country
    {
        $country = $this->factory()->country(['isoalpha2' => $alpha2, 'isoalpha3' => $alpha2.'X', 'isocode' => $isoCode]);
        $country->setZipCodeFormat($zipCodeFormat)->setNeedZipCode((int) $needZipCode);

        foreach (LangQuery::create()->find() as $lang) {
            $country->setLocale($lang->getLocale())->setTitle('Country '.$alpha2);
        }
        $country->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($this->getPropelConnection());

        return $country;
    }

    private function zoneUrl(Area $area): string
    {
        return '/admin/configuration/shipping_configuration/update/'.$area->getId();
    }

    private function area(): Area
    {
        $area = (new Area())->setName('Zone with postal codes');
        $area->save($this->getPropelConnection());

        return $area;
    }

    private function rule(Area $area, string $pattern, bool $excluded): AreaZipCodeRule
    {
        $rule = (new AreaZipCodeRule())->setAreaId($area->getId())->setPattern($pattern)->setExcluded($excluded);
        $rule->save($this->getPropelConnection());

        return $rule;
    }

    private function factory(): FixtureFactory
    {
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }
}
