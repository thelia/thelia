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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\Area;
use Thelia\Model\AreaZipCodeRule;
use Thelia\Model\AreaZipCodeRuleQuery;
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

        $form = $this->client->getCrawler()->filter('[data-testid="shipping-configuration-zip-code-rule-'.$rule->getId().'"] form')->form();
        $this->client->submit($form);

        self::assertNull(AreaZipCodeRuleQuery::create()->findPk($rule->getId()));
    }

    public function testAnAdministratorWithoutTheRightToTheZonesChangesNoRule(): void
    {
        $area = $this->area();
        $rule = $this->rule($area, '20*', true);
        $this->loginAs($this->factory()->admin());
        $this->assertPageRenders($this->zoneUrl($area));
        $crawler = $this->client->getCrawler();
        $add = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-form"]')->form();
        $delete = $crawler->filter('[data-testid="shipping-configuration-zip-code-rule-'.$rule->getId().'"] form')->form();

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
