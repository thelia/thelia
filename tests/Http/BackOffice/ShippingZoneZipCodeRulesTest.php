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
use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\Criteria;
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
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\State;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The postal code card of a shipping zone: the rules it lists, adding and deleting one,
 * and the refusals the core makes readable.
 */
final class ShippingZoneZipCodeRulesTest extends WebIntegrationTestCase
{
    /**
     * Written as a string: `::class` on a class the installed theme lacks resolves silently.
     */
    private const string THEME_ZIP_CODE_FORMAT = 'BackOfficeDefaultTwigBundle\\Service\\Shipping\\ZipCodeFormat';
    private const string THEME_ZIP_CODE_LOG = 'BackOfficeDefaultTwigBundle\\Service\\Shipping\\ZipCodeLog';

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

    /**
     * Country::getZipCodeRE() reads C as any characters and copies every other character
     * unescaped into a regular expression: such a format leaves free text.
     */
    public function testTheCardWarnsAboutAFormatThatLeavesFreeText(): void
    {
        $this->skipUnlessTheThemeKnowsTheFormatsThatLeaveFreeText();
        $area = $this->area();
        $checked = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $anyCharactersFirst = $this->countryOfTheZone($area, 'XQ', '998', 'CNNN', true);
        $twoFreeCharacters = $this->countryOfTheZone($area, 'XR', '991', 'CC NNN', true);
        $aDot = $this->countryOfTheZone($area, 'XS', '992', 'NN.NNN', true);
        $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders($this->zoneUrl($area));
        $warning = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rules-unformatted"]')->text();
        self::assertStringContainsString($anyCharactersFirst->getTitle(), $warning, 'C stands for any characters.');
        self::assertStringContainsString($twoFreeCharacters->getTitle(), $warning);
        self::assertStringContainsString($aDot->getTitle(), $warning, 'A dot is any character to the regular expression.');
        self::assertStringNotContainsString($checked->getTitle(), $warning);
        self::assertStringContainsString('the format leaves free text', $warning);
    }

    /**
     * The merchant's trace of a country removed from a zone: the administration log names the
     * postal code rules that left with it, read before they went.
     *
     * @return iterable<string, array{string}>
     */
    public static function removals(): iterable
    {
        yield 'one country' => ['/admin/configuration/shipping_configuration/country/remove'];
        yield 'the countries ticked' => ['/admin/configuration/shipping_configuration/countries/remove'];
    }

    #[DataProvider('removals')]
    public function testRemovingACountryLogsThePostalCodeRulesThatLeaveWithIt(string $path): void
    {
        $this->skipUnlessTheThemeKnowsTheFormatsThatLeaveFreeText();
        $area = $this->area();
        $country = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $this->rule($area, '20*', true)->setCountryId($country->getId())->save($this->getPropelConnection());
        $this->rule($area, '75001...75020', false)->setCountryId($country->getId())->save($this->getPropelConnection());
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $token = (string) $this->client->getCrawler()->filter('[data-testid="shipping-configuration-add-country-form"] input[name="_token"]')->attr('value');

        $countryField = str_ends_with($path, 'countries/remove') ? ['country_id' => [(string) $country->getId()]] : ['country_id' => (string) $country->getId()];
        $this->client->request('POST', $path, ['area_id' => (string) $area->getId(), '_token' => $token] + $countryField);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame(0, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'The rules left with the country.');
        self::assertSame(1, $this->logEntries(
            $area,
            \sprintf('Country %d removed from shipping zone "%s" (ID %d), with its 2 postal code rule(s): excluded 20*, included 75001...75020', $country->getId(), $area->getName(), $area->getId()),
        ), 'The administration log names the rules that left with the country.');
    }

    public function testRemovingAStateOfACountryThatStaysLogsNoRule(): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $country = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $state = (new State())->setCountryId($country->getId())->setIsocode('XV1')->setVisible(1);
        $state->save($this->getPropelConnection());
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->setStateId($state->getId())->save($this->getPropelConnection());
        $this->rule($area, '20*', true)->setCountryId($country->getId())->save($this->getPropelConnection());

        $this->removeFromTheZone($area, ['country_id' => (string) $country->getId(), 'state_id' => (string) $state->getId()]);

        self::assertSame(1, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'The country stays: so do its rules.');
        self::assertSame(1, $this->logEntries($area, \sprintf('Country %d (state %d) removed from shipping zone "%s" (ID %d)', $country->getId(), $state->getId(), $area->getName(), $area->getId())));
    }

    /**
     * A country without a rule: the log says it left, and no more. The name of the zone, the
     * merchant's text, is escaped as the patterns are.
     */
    public function testRemovingACountryWithoutARuleLogsTheRemovalOnly(): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $area->setName("Zone\r\n[forged]")->save($this->getPropelConnection());
        $country = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);

        $this->removeFromTheZone($area, ['country_id' => (string) $country->getId()]);

        self::assertSame(1, $this->logEntries($area, \sprintf('Country %d removed from shipping zone "Zone\\r\\n[forged]" (ID %d)', $country->getId(), $area->getId())));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function removalPaths(): iterable
    {
        yield 'one country' => ['/admin/configuration/shipping_configuration/country/remove'];
        yield 'the countries ticked' => ['/admin/configuration/shipping_configuration/countries/remove'];
    }

    #[DataProvider('removalPaths')]
    public function testRemovingACountryTheZoneDoesNotHoldLogsNothing(string $path): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $elsewhere = $this->factory()->country(['isoalpha2' => 'XW', 'isoalpha3' => 'XWX', 'isocode' => '996']);
        $countryField = str_ends_with($path, 'countries/remove') ? ['country_id' => [(string) $elsewhere->getId()]] : ['country_id' => (string) $elsewhere->getId()];

        $this->removeFromTheZone($area, $countryField, $path);

        self::assertSame(0, AdminLogQuery::create()->filterByResource(AdminResources::AREA)->filterByResourceId($area->getId())->filterByMessage('Country %', Criteria::LIKE)->count(), 'Nothing left the zone.');
    }

    /**
     * Two states of a country ticked together: the country leaves with the second, and its
     * rules with it. The log names them once, on the removal that took them away.
     */
    public function testRemovingTheStatesOfACountryLogsItsRulesOnce(): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $country = $this->factory()->country(['isoalpha2' => 'XV', 'isoalpha3' => 'XVX', 'isocode' => '995']);
        $states = [];
        foreach (['XV1', 'XV2'] as $isoCode) {
            $state = (new State())->setCountryId($country->getId())->setIsocode($isoCode)->setVisible(1);
            $state->save($this->getPropelConnection());
            (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->setStateId($state->getId())->save($this->getPropelConnection());
            $states[] = $state;
        }
        $this->rule($area, '20*', true)->setCountryId($country->getId())->save($this->getPropelConnection());

        $this->removeFromTheZone(
            $area,
            ['country_id' => [$country->getId().'-'.$states[0]->getId(), $country->getId().'-'.$states[1]->getId()]],
            '/admin/configuration/shipping_configuration/countries/remove',
        );

        self::assertSame(0, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'The rules left with the country.');
        self::assertSame(1, $this->logEntries($area, \sprintf('Country %d (state %d) removed from shipping zone "%s" (ID %d)', $country->getId(), $states[0]->getId(), $area->getName(), $area->getId())));
        self::assertSame(1, $this->logEntries($area, \sprintf('Country %d (state %d) removed from shipping zone "%s" (ID %d), with its 1 postal code rule(s): excluded 20*', $country->getId(), $states[1]->getId(), $area->getName(), $area->getId())));
        self::assertSame(2, $this->removalEntries($area), 'One line for each state that left.');
    }

    public function testACountryTickedTwiceIsLoggedOnce(): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $country = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);

        $this->removeFromTheZone(
            $area,
            ['country_id' => [(string) $country->getId(), (string) $country->getId()]],
            '/admin/configuration/shipping_configuration/countries/remove',
        );

        self::assertSame(1, $this->logEntries($area, \sprintf('Country %d removed from shipping zone "%s" (ID %d)', $country->getId(), $area->getName(), $area->getId())));
        self::assertSame(1, $this->removalEntries($area));
    }

    /**
     * Rules kept for a country the zone no longer holds go when the country is removed
     * again: no country left the zone, the rules did, and the log says so.
     */
    #[DataProvider('removalPaths')]
    public function testRulesOfACountryTheZoneDoesNotHoldAreLoggedWhenTheyGo(string $path): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $gone = $this->factory()->country(['isoalpha2' => 'XW', 'isoalpha3' => 'XWX', 'isocode' => '996']);
        $this->rule($area, '20*', true)->setCountryId($gone->getId())->save($this->getPropelConnection());
        $countryField = str_ends_with($path, 'countries/remove') ? ['country_id' => [(string) $gone->getId()]] : ['country_id' => (string) $gone->getId()];

        $this->removeFromTheZone($area, $countryField, $path);

        self::assertSame(0, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'The rules went.');
        self::assertSame(1, $this->logEntries($area, \sprintf('Postal code rule(s) of country %d deleted from shipping zone "%s" (ID %d): excluded 20*', $gone->getId(), $area->getName(), $area->getId())));
        self::assertSame(0, $this->removalEntries($area), 'No country left the zone.');
    }

    /**
     * Refused before the zone is looked up: an administrator who may only view the zones
     * learns nothing of which exist.
     */
    #[DataProvider('removalPaths')]
    public function testAnAdministratorWithoutTheRightToTheZonesIsRefusedBeforeTheZoneIsRead(string $path): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $this->loginAs($this->factory()->restrictedAdmin([AdminResources::AREA => [AccessManager::VIEW]]));

        $this->client->request('POST', $path, ['area_id' => '999999999', 'country_id' => '1', '_token' => 'irrelevant']);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTheNameOfACreatedZoneIsEscapedInTheAdministrationLog(): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders('/admin/configuration/shipping_configuration');
        $form = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-create-form"]')->form();

        $this->client->submit($form, ['thelia_area_create[name]' => "Zone\r\n[forged]"]);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame(1, AdminLogQuery::create()
            ->filterByResource(AdminResources::AREA)
            ->filterByMessage('Shipping zone "Zone\\\\r\\\\n[forged]" (ID %) created', Criteria::LIKE)
            ->count());
    }

    /**
     * A removal posted with a forged token changes nothing, and the log says nothing left.
     * The one removal goes back to the zone (AdminFormAction::tokenAction() catches the
     * refusal), the removal of the countries ticked answers 403. The same request with the
     * token of the page does remove the country.
     */
    #[DataProvider('removalPaths')]
    public function testARemovalWithAnInvalidTokenLeavesTheZoneAsItIs(string $path): void
    {
        $this->skipUnlessTheThemeLogsWhatLeftTheZone();
        $area = $this->area();
        $country = $this->countryOfTheZone($area, 'XV', '995', 'NNNNN', true);
        $this->rule($area, '20*', true)->setCountryId($country->getId())->save($this->getPropelConnection());
        $this->loginAs($this->factory()->admin());
        $countryField = str_ends_with($path, 'countries/remove') ? ['country_id' => [(string) $country->getId()]] : ['country_id' => (string) $country->getId()];

        $this->client->request('POST', $path, ['area_id' => (string) $area->getId(), '_token' => 'forged'] + $countryField);

        $response = $this->client->getResponse();
        if (str_ends_with($path, 'countries/remove')) {
            self::assertSame(403, $response->getStatusCode());
            self::assertStringContainsString('Invalid or expired security token.', (string) $response->getContent());
        } else {
            self::assertTrue($response->isRedirect($this->zoneUrl($area)), 'The one removal goes back to the zone.');
        }
        self::assertSame(1, CountryAreaQuery::create()->filterByAreaId($area->getId())->filterByCountryId($country->getId())->count(), 'The country is still in the zone.');
        self::assertSame(1, AreaZipCodeRuleQuery::create()->filterByAreaId($area->getId())->count(), 'Its rule is still there.');
        self::assertSame(0, AdminLogQuery::create()->filterByResource(AdminResources::AREA)->filterByResourceId($area->getId())->count(), 'Nothing is logged as removed.');

        $this->removeFromTheZone($area, $countryField, $path);

        self::assertSame(0, CountryAreaQuery::create()->filterByAreaId($area->getId())->filterByCountryId($country->getId())->count(), 'With the token of the page, the route removes the country.');
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

    public function testARuleOfAnotherZoneIsNotDeletedFromThisOne(): void
    {
        $area = $this->area();
        $own = $this->rule($area, '75*', false);
        $otherZonesRule = $this->rule($this->area(), '20*', true);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));

        $form = $this->deleteForm($own);
        $form['rule_id']->setValue((string) $otherZonesRule->getId());
        $this->client->submit($form);

        self::assertNotNull(AreaZipCodeRuleQuery::create()->findPk($otherZonesRule->getId()), 'The rule of another zone was deleted.');
        $this->client->followRedirect();
        self::assertStringContainsString('This postal code rule does not exist anymore.', $this->client->getCrawler()->filter('[data-testid="bo-flash-danger"]')->text());
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

    /**
     * @param array<string, string|list<string>> $fields
     */
    private function removeFromTheZone(Area $area, array $fields, string $path = '/admin/configuration/shipping_configuration/country/remove'): void
    {
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $token = (string) $this->client->getCrawler()->filter('[data-testid="shipping-configuration-add-country-form"] input[name="_token"]')->attr('value');

        $this->client->request('POST', $path, ['area_id' => (string) $area->getId(), '_token' => $token] + $fields);

        self::assertTrue($this->client->getResponse()->isRedirect());
    }

    private function logEntries(Area $area, string $message): int
    {
        return AdminLogQuery::create()
            ->filterByResource(AdminResources::AREA)
            ->filterByResourceId($area->getId())
            ->filterByMessage($message)
            ->count();
    }

    private function removalEntries(Area $area): int
    {
        return AdminLogQuery::create()
            ->filterByResource(AdminResources::AREA)
            ->filterByResourceId($area->getId())
            ->filterByMessage('Country %', Criteria::LIKE)
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

    /**
     * The release of the theme that reads the formats leaving free text, gets the merchant's
     * trace of a country removed with its rules and tells a refused rule from another
     * refusal. A core test of it must stay green while the theme of the core CI predates it.
     */
    private function skipUnlessTheThemeKnowsTheFormatsThatLeaveFreeText(): void
    {
        if (!class_exists(self::THEME_ZIP_CODE_FORMAT)) {
            self::markTestSkipped('The installed back-office theme predates the review of the postal code rules.');
        }
    }

    /**
     * The release of the theme that logs each pair (country, state) that left a zone once,
     * the rules on the removal that took them away, and checks the right to the zones before
     * reading one.
     */
    private function skipUnlessTheThemeLogsWhatLeftTheZone(): void
    {
        if (!class_exists(self::THEME_ZIP_CODE_LOG)) {
            self::markTestSkipped('The installed back-office theme predates the administration log of what left a zone.');
        }
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
