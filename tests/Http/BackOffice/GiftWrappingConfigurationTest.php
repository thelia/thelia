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

use BackOfficeDefaultTwigBundle\Controller\Configuration\GiftWrappingController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\GiftWrapping;
use Thelia\Model\GiftWrappingQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\TaxRuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Back-office volet of the gift wrapping US: the "Gift wrappings" CRUD screen
 * (BackOfficeDefaultTwigBundle\Controller\Configuration\GiftWrappingController) and the
 * read-only gift block on the order detail page.
 */
final class GiftWrappingConfigurationTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    protected function setUp(): void
    {
        // A skip rather than a failure: the core ships with whichever back-office theme
        // it is given, and one that predates the gift wrappings has none of these screens.
        if (!class_exists(GiftWrappingController::class)) {
            self::markTestSkipped('The installed back-office theme predates the gift wrappings.');
        }

        parent::setUp();

        $this->injector = new AdminSessionInjector();

        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }
        parent::tearDown();
    }

    public function testTheListIsServedAndSaysSoWhenNothingIsDefined(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/configuration/gift-wrapping');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'data-testid="gift-wrappings-page"',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testCreatingAWrappingPersistsIt(): void
    {
        $this->loginAdmin();

        $code = 'gift_box_'.uniqid();

        $crawler = $this->client->request('GET', '/admin/configuration/gift-wrapping');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $button = $crawler->filter('[data-testid="gift-wrapping-create-submit"]');
        self::assertGreaterThan(0, $button->count(), 'The list must expose its create submit button.');

        $form = $button->form([
            'thelia_gift_wrapping_creation[code]' => $code,
            'thelia_gift_wrapping_creation[title]' => 'Gift box',
            'thelia_gift_wrapping_creation[price]' => '3',
            'thelia_gift_wrapping_creation[tax_rule_id]' => (string) $this->aTaxRuleId(),
        ]);

        $this->client->submit($form);

        self::assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'Creating a valid wrapping must redirect (a 200 here means the form was rejected).',
        );

        $created = GiftWrappingQuery::create()->findOneByCode($code);

        self::assertNotNull($created, 'The wrapping must be persisted.');
        self::assertSame(3.0, (float) $created->getPrice());
        self::assertTrue($created->isActive(), 'A freshly created wrapping defaults to active.');
    }

    /**
     * Zero is a wrapping the shop offers, not a missing price: the form has to take it.
     */
    public function testAWrappingMayBeCreatedFree(): void
    {
        $this->loginAdmin();

        $code = 'kraft_paper_'.uniqid();

        $crawler = $this->client->request('GET', '/admin/configuration/gift-wrapping');
        $form = $crawler->filter('[data-testid="gift-wrapping-create-submit"]')->form([
            'thelia_gift_wrapping_creation[code]' => $code,
            'thelia_gift_wrapping_creation[title]' => 'Kraft paper',
            'thelia_gift_wrapping_creation[price]' => '0',
            'thelia_gift_wrapping_creation[tax_rule_id]' => (string) $this->aTaxRuleId(),
        ]);

        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $created = GiftWrappingQuery::create()->findOneByCode($code);

        self::assertNotNull($created);
        self::assertTrue($created->isFree());
    }

    public function testAWrappingCanBeRepricedAndTurnedOffFromItsEditScreen(): void
    {
        $this->loginAdmin();

        $wrapping = $this->createGiftWrapping('gift_box_'.uniqid(), '3.000000', 'Gift box');

        $crawler = $this->client->request('GET', \sprintf('/admin/configuration/gift-wrapping/update/%d', $wrapping->getId()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('[data-testid="gift-wrapping-edit-submit"]')->form();
        $form['thelia_gift_wrapping_modification[price]'] = '9';
        $form['thelia_gift_wrapping_modification[active]']->untick();
        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $reread = GiftWrappingQuery::create()->findPk($wrapping->getId());

        self::assertSame(9.0, (float) $reread?->getPrice());
        self::assertFalse($reread?->isActive());
    }

    /**
     * The code names the wrapping on every order line it produced: it is picked once and
     * frozen, so the edit screen must not let it be rewritten.
     */
    public function testTheCodeCannotBeChangedOnceTheWrappingExists(): void
    {
        $this->loginAdmin();

        $wrapping = $this->createGiftWrapping('gift_box_'.uniqid(), '3.000000', 'Gift box');

        $crawler = $this->client->request('GET', \sprintf('/admin/configuration/gift-wrapping/update/%d', $wrapping->getId()));
        $codeField = $crawler->filter('input[name="thelia_gift_wrapping_modification[code]"]');

        self::assertGreaterThan(0, $codeField->count());
        self::assertNotNull($codeField->attr('disabled'), 'The code of an existing wrapping is read-only.');
    }

    public function testTheOrderSheetShowsTheServiceAndTheNoteForTheRecipient(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order();
        $order->setGiftMessage('Happy birthday, Mum!')->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId((int) $order->getId())
            ->setProductRef('gift-box')
            ->setProductSaleElementsRef('gift-box')
            ->setTitle('Gift box')
            ->setQuantity(1)
            ->setPrice('3.000000')
            ->setPromoPrice('0.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->setIsOffered(0)
            ->setTaxRuleTitle('VAT 20')
            ->setLineType(OrderProduct::LINE_TYPE_SERVICE)
            ->save($this->getPropelConnection());

        $this->client->request('GET', \sprintf('/admin/order/update/%d', $order->getId()));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('data-testid="order-gift"', $content);
        self::assertStringContainsString('Gift box', $content);
        self::assertStringContainsString('Happy birthday, Mum!', $content);
    }

    public function testTheOrderSheetHidesTheGiftBlockWhenThereIsNoGift(): void
    {
        $this->loginAdmin();

        $factory = new FixtureFactory($this->getPropelConnection());
        $order = $factory->order();

        $this->client->request('GET', \sprintf('/admin/order/update/%d', $order->getId()));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(
            'data-testid="order-gift"',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    // ------------------------------------------------------------------
    // Harness
    // ------------------------------------------------------------------

    private function createGiftWrapping(string $code, string $price, string $title): GiftWrapping
    {
        $giftWrapping = (new GiftWrapping())
            ->setCode($code)
            ->setPrice($price)
            ->setTaxRuleId($this->aTaxRuleId())
            ->setActive(1)
            ->setTitle($title);
        $giftWrapping->save($this->getPropelConnection());

        return $giftWrapping;
    }

    private function aTaxRuleId(): int
    {
        $taxRule = TaxRuleQuery::create()->findOne($this->getPropelConnection())
            ?? self::fail('The shop must ship with a tax rule.');

        return (int) $taxRule->getId();
    }

    private function loginAdmin(): void
    {
        // FixtureFactory built directly, not via createFixtureFactory(): that helper
        // pushes a synthetic Request onto the stack, which would then become the "main"
        // request the security context resolves the session from.
        $factory = new FixtureFactory($this->getPropelConnection());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }
}
