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

namespace Thelia\Tests\Integration\Quote;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Company;
use Thelia\Model\CompanyRoleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequest;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionAddress;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Model\ResourceQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What the quote tables hold and refuse, whoever writes them: the models, then the rules of the
 * database (unique reference, unique revision number, cascade, set null) that no code is left to
 * keep, then what the install seeds for the quotes.
 */
final class QuoteSchemaTest extends IntegrationTestCase
{
    private FixtureFactory $fixtures;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
    }

    public function testAQuoteKeepsWhatItIsGivenAndTheDefaultsOfItsColumns(): void
    {
        $quote = $this->quote();

        $reloaded = QuoteQuery::create()->findPk($quote->getId());

        self::assertNotNull($reloaded);
        self::assertSame('requested', $reloaded->getState());
        self::assertSame(1, $reloaded->getCurrentRevision(), 'A quote stands at its first revision.');
        self::assertSame(0, $reloaded->getCouponDropped(), 'TINYINT reads as an int.');
        self::assertNull($reloaded->getCompanyId());
        self::assertNull($reloaded->getLocale());
        self::assertNull($reloaded->getBuyerComment());
        self::assertNull($reloaded->getNeededBy());
        self::assertNull($reloaded->getValidUntil());
        self::assertNull($reloaded->getSentAt());
        self::assertNull($reloaded->getRemindedAt());
        self::assertNull($reloaded->getInvoiceAddressId());
        self::assertNull($reloaded->getDeliveryAddressId());
        self::assertNull($reloaded->getDeliveryModuleId());
        self::assertNotNull($reloaded->getCreatedAt());
    }

    public function testAQuoteKeepsItsDatesItsAddressesAndItsCarrier(): void
    {
        $customer = $this->customer();
        $address = $this->fixtures->address($customer);
        $module = $this->fixtures->paymentModule();

        $quote = $this->quote($customer);
        $quote->setLocale('fr_FR');
        $quote->setBuyerComment('Delivery on a pallet');
        $quote->setNeededBy('2026-12-24');
        $quote->setValidUntil('2026-12-16');
        $quote->setSentAt('2026-12-01 10:30:00');
        $quote->setInvoiceAddressId($address->getId());
        $quote->setDeliveryAddressId($address->getId());
        $quote->setDeliveryModuleId($module->getId());
        $quote->save($this->connection());

        $reloaded = QuoteQuery::create()->findPk($quote->getId());

        self::assertSame('fr_FR', $reloaded->getLocale());
        self::assertSame('Delivery on a pallet', $reloaded->getBuyerComment());
        self::assertSame('2026-12-24', $reloaded->getNeededBy('Y-m-d'));
        self::assertSame('2026-12-16', $reloaded->getValidUntil('Y-m-d'));
        self::assertSame('2026-12-01 10:30:00', $reloaded->getSentAt('Y-m-d H:i:s'));
        self::assertSame($address->getId(), $reloaded->getInvoiceAddress()->getId());
        self::assertSame($address->getId(), $reloaded->getDeliveryAddress()->getId());
        self::assertSame($module->getId(), $reloaded->getDeliveryModule()->getId());
    }

    public function testTwoQuotesNeverShareAReference(): void
    {
        $quote = $this->quote();

        $this->assertRefusedByTheDatabase(\sprintf(
            "INSERT INTO `quote` (`ref`, `customer_id`, `state`, `currency_id`) VALUES ('%s', %d, 'requested', %d)",
            $quote->getRef(),
            $quote->getCustomerId(),
            $quote->getCurrencyId(),
        ));
    }

    public function testARevisionNumberIsUsedOnceByQuote(): void
    {
        $quote = $this->quote();
        $this->revision($quote, 1);

        $this->assertRefusedByTheDatabase(\sprintf(
            "INSERT INTO `quote_revision` (`quote_id`, `number`, `author`, `state`) VALUES (%d, 1, 'admin', 'draft')",
            $quote->getId(),
        ));

        $other = $this->quote();
        $this->revision($other, 1);

        self::assertSame(1, QuoteRevisionQuery::create()->filterByQuoteId($other->getId())->count(), 'Another quote may use the same number.');
    }

    public function testARevisionKeepsItsMoneyAsDecimalStringsAndStartsFromADraftWithNoDiscount(): void
    {
        $revision = $this->revision($this->quote(), 2);

        $reloaded = QuoteRevisionQuery::create()->findPk($revision->getId());

        self::assertSame(2, $reloaded->getNumber());
        self::assertSame('0.000000', $reloaded->getDiscount());
        self::assertSame('0.000000', $reloaded->getPostage());
        self::assertNull($reloaded->getTotalUntaxed(), 'The totals are frozen when the revision is sent.');
        self::assertNull($reloaded->getTotalTaxed());
        self::assertNull($reloaded->getMerchantNote());
        self::assertNull($reloaded->getAdminId());
        self::assertNull($reloaded->getSentAt());

        $reloaded->setDiscount('30.000000');
        $reloaded->setPostage('15.000000');
        $reloaded->setTotalUntaxed('1375.000000');
        $reloaded->setTotalTaxed('1620.000000');
        $reloaded->save($this->connection());

        $again = QuoteRevisionQuery::create()->findPk($revision->getId());

        self::assertSame('30.000000', $again->getDiscount());
        self::assertSame('15.000000', $again->getPostage());
        self::assertSame('1375.000000', $again->getTotalUntaxed());
        self::assertSame('1620.000000', $again->getTotalTaxed());
    }

    public function testARevisionHasNothingFrozenAndNothingChosenUntilItIsWritten(): void
    {
        $reloaded = QuoteRevisionQuery::create()->findPk($this->revision($this->quote(), 1)->getId());

        self::assertNull($reloaded->getItemsUntaxed());
        self::assertNull($reloaded->getItemsTax());
        self::assertNull($reloaded->getPostageTax());
        self::assertNull($reloaded->getReverseCharge());
        self::assertNull($reloaded->getValidUntil());
        self::assertNull($reloaded->getDeliveryModuleId());
    }

    public function testARevisionKeepsWhatItWasFrozenWithAsWrittenAndTheCarrierItProposes(): void
    {
        $module = $this->fixtures->paymentModule();
        $revision = $this->revision($this->quote(), 2);
        $revision
            ->setItemsUntaxed('1360.000000')
            ->setItemsTax('272.000000')
            ->setPostageTax('3.000000')
            ->setReverseCharge(1)
            ->setValidUntil('2026-12-31')
            ->setDeliveryModuleId($module->getId())
            ->save($this->connection());

        $again = QuoteRevisionQuery::create()->findPk($revision->getId());

        self::assertSame('1360.000000', $again->getItemsUntaxed());
        self::assertSame('272.000000', $again->getItemsTax());
        self::assertSame('3.000000', $again->getPostageTax());
        self::assertSame(1, $again->getReverseCharge(), 'TINYINT reads as an int.');
        self::assertSame('2026-12-31', $again->getValidUntil('Y-m-d'));
        self::assertSame($module->getId(), $again->getDeliveryModuleId());

        $this->connection()->exec('DELETE FROM `module` WHERE `id` = '.$module->getId());

        self::assertNull(QuoteRevisionQuery::create()->findPk($revision->getId())?->getDeliveryModuleId(), 'the revision stays when the carrier goes');
    }

    public function testARevisionHoldsOneCopyOfEachAddressAtMost(): void
    {
        $revision = $this->revision($this->quote(), 1);
        $this->copyOfAnAddress($revision, 'invoice');
        $this->copyOfAnAddress($revision, 'delivery');

        $this->assertRefusedByTheDatabase(\sprintf(
            "INSERT INTO `quote_revision_address` (`quote_revision_id`, `kind`) VALUES (%d, 'invoice')",
            $revision->getId(),
        ));

        $other = $this->revision($this->quote(), 1);
        $this->copyOfAnAddress($other, 'invoice');

        self::assertSame(1, QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($other->getId())->count(), 'Another revision keeps its own.');
    }

    public function testTheCopiesOfTheAddressesGoWithTheirRevisionAndKeepTheirCountryTitleWhenTheCountryGoes(): void
    {
        $country = $this->fixtures->country(['isocode' => '997', 'isoalpha2' => 'ZX', 'isoalpha3' => 'ZXX', 'shopCountry' => false]);
        $revision = $this->revision($this->quote(), 1);
        $copy = $this->copyOfAnAddress($revision, 'delivery');
        $copy->setCountryId($country->getId())->setCountryTitle('Zedland')->save($this->connection());

        $this->connection()->exec('DELETE FROM `country` WHERE `id` = '.$country->getId());

        $kept = QuoteRevisionAddressQuery::create()->findPk($copy->getId());
        self::assertNotNull($kept);
        self::assertNull($kept->getCountryId());
        self::assertSame('Zedland', $kept->getCountryTitle());

        $this->connection()->exec('DELETE FROM `quote_revision` WHERE `id` = '.$revision->getId());

        self::assertNull(QuoteRevisionAddressQuery::create()->findPk($copy->getId()));
    }

    public function testALineKeepsItsLabelsItsQuantityAndItsPricesAsTheyWereWritten(): void
    {
        $item = $this->item($this->revision($this->quote(), 1));

        $reloaded = QuoteItemQuery::create()->findPk($item->getId());

        self::assertSame('PROD-A', $reloaded->getProductRef());
        self::assertSame('PROD-A-1', $reloaded->getProductSaleElementsRef());
        self::assertSame('Product A', $reloaded->getTitle());
        self::assertSame(12.5, $reloaded->getQuantity(), 'A quantity is a float, like the one of an order line.');
        self::assertSame('100.000000', $reloaded->getCatalogPrice());
        self::assertSame('90.000000', $reloaded->getPrice());
        self::assertNull($reloaded->getProductId());
        self::assertNull($reloaded->getTaxRuleId());
        self::assertNull($reloaded->getTaxRuleTitle());
    }

    public function testALineKeepsTheProductAndTheTaxRuleItWasWrittenFor(): void
    {
        $product = $this->product();
        $taxRule = $this->fixtures->taxRule();
        $item = $this->item($this->revision($this->quote(), 1), [
            'product' => $product,
            'taxRule' => $taxRule,
        ]);

        $reloaded = QuoteItemQuery::create()->findPk($item->getId());

        self::assertSame($product->getId(), $reloaded->getProduct()->getId());
        self::assertSame($product->getDefaultSaleElements()->getId(), $reloaded->getProductSaleElements()->getId());
        self::assertSame($taxRule->getId(), $reloaded->getTaxRule()->getId());
    }

    public function testALineHasAtMostOneRequestOfTheBuyer(): void
    {
        $item = $this->item($this->revision($this->quote(), 1));

        $request = new QuoteItemRequest();
        $request->setQuoteItemId($item->getId());
        $request->setPrice('85.000000');
        $request->save($this->connection());

        $reloaded = QuoteItemRequestQuery::create()->findPk($request->getId());

        self::assertSame('85.000000', $reloaded->getPrice());
        self::assertNull($reloaded->getQuantity(), 'A buyer who leaves the quantity as it is asks for none.');
        self::assertNotNull($reloaded->getCreatedAt());

        $this->assertRefusedByTheDatabase(\sprintf('INSERT INTO `quote_item_request` (`quote_item_id`, `quantity`) VALUES (%d, 3)', $item->getId()));
    }

    public function testDeletingAQuoteTakesItsRevisionsItsLinesAndTheirRequests(): void
    {
        $quote = $this->quote();
        $revision = $this->revision($quote, 1);
        $item = $this->item($revision);
        $request = new QuoteItemRequest();
        $request->setQuoteItemId($item->getId());
        $request->setQuantity(3.0);
        $request->save($this->connection());

        $this->connection()->exec('DELETE FROM `quote` WHERE `id` = '.$quote->getId());

        self::assertNull(QuoteRevisionQuery::create()->findPk($revision->getId()));
        self::assertNull(QuoteItemQuery::create()->findPk($item->getId()));
        self::assertNull(QuoteItemRequestQuery::create()->findPk($request->getId()));
    }

    public function testDeletingACustomerTakesItsQuotes(): void
    {
        $customer = $this->customer();
        $quote = $this->quote($customer);

        $this->connection()->exec('DELETE FROM `customer` WHERE `id` = '.$customer->getId());

        self::assertNull(QuoteQuery::create()->findPk($quote->getId()));
    }

    public function testDeletingTheCompanyTheAddressesOrTheCarrierOfAQuoteKeepsTheQuote(): void
    {
        $customer = $this->customer();
        $company = new Company();
        $company->setName('Acme');
        $company->save($this->connection());
        $address = $this->fixtures->address($customer);
        $module = $this->fixtures->paymentModule();

        $quote = $this->quote($customer);
        $quote->setCompanyId($company->getId());
        $quote->setInvoiceAddressId($address->getId());
        $quote->setDeliveryAddressId($address->getId());
        $quote->setDeliveryModuleId($module->getId());
        $quote->save($this->connection());

        $this->connection()->exec('DELETE FROM `company` WHERE `id` = '.$company->getId());
        $this->connection()->exec('DELETE FROM `address` WHERE `id` = '.$address->getId());
        $this->connection()->exec('DELETE FROM `module` WHERE `id` = '.$module->getId());

        $reloaded = QuoteQuery::create()->findPk($quote->getId());

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getCompanyId());
        self::assertNull($reloaded->getInvoiceAddressId());
        self::assertNull($reloaded->getDeliveryAddressId());
        self::assertNull($reloaded->getDeliveryModuleId());
    }

    public function testAQuoteInUseKeepsItsCurrency(): void
    {
        $quote = $this->quote();

        $this->assertRefusedByTheDatabase('DELETE FROM `currency` WHERE `id` = '.$quote->getCurrencyId());
    }

    public function testDeletingTheAdminOrTheProductOfALineKeepsTheRevisionAndTheLineReadable(): void
    {
        $admin = $this->fixtures->admin();
        $product = $this->product();
        $revision = $this->revision($this->quote(), 1, $admin);
        $item = $this->item($revision, ['product' => $product]);

        $this->connection()->exec('DELETE FROM `admin` WHERE `id` = '.$admin->getId());
        $this->connection()->exec('DELETE FROM `cart_item` WHERE `product_id` = '.$product->getId());
        $this->connection()->exec('DELETE FROM `product_sale_elements` WHERE `product_id` = '.$product->getId());
        $this->connection()->exec('DELETE FROM `product` WHERE `id` = '.$product->getId());

        $reloadedRevision = QuoteRevisionQuery::create()->findPk($revision->getId());
        $reloadedItem = QuoteItemQuery::create()->findPk($item->getId());

        self::assertNotNull($reloadedRevision);
        self::assertNull($reloadedRevision->getAdminId());
        self::assertNotNull($reloadedItem);
        self::assertNull($reloadedItem->getProductId());
        self::assertNull($reloadedItem->getProductSaleElementsId());
        self::assertSame('Product A', $reloadedItem->getTitle());
        self::assertSame('PROD-A', $reloadedItem->getProductRef());
    }

    public function testAQuoteGivesOneOrderAtMostAndAnOrderOfNoQuoteIsFree(): void
    {
        $quote = $this->quote();
        $first = $this->fixtures->order($quote->getCustomer());
        $first->setQuoteId($quote->getId());
        $first->save($this->connection());

        self::assertSame($quote->getId(), OrderQuery::create()->findPk($first->getId())->getQuoteId());
        self::assertSame($first->getId(), $quote->getOrders()->getFirst()->getId());

        $second = $this->fixtures->order($quote->getCustomer());

        $this->assertRefusedByTheDatabase(\sprintf('UPDATE `order` SET `quote_id` = %d WHERE `id` = %d', $quote->getId(), $second->getId()));

        $third = $this->fixtures->order($quote->getCustomer());

        self::assertNull(OrderQuery::create()->findPk($second->getId())->getQuoteId());
        self::assertNull(OrderQuery::create()->findPk($third->getId())->getQuoteId(), 'Orders of no quote do not collide on the unique key.');
    }

    public function testDeletingAQuoteLeavesItsOrderWithNoQuote(): void
    {
        $quote = $this->quote();
        $order = $this->fixtures->order($quote->getCustomer());
        $order->setQuoteId($quote->getId());
        $order->save($this->connection());

        $this->connection()->exec('DELETE FROM `quote` WHERE `id` = '.$quote->getId());

        $reloaded = OrderQuery::create()->findPk($order->getId());

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getQuoteId());
    }

    public function testAnOrderVersionKeepsTheQuoteTheOrderCameFrom(): void
    {
        $quote = $this->quote();
        $order = $this->fixtures->order($quote->getCustomer());
        $order->setQuoteId($quote->getId());
        $order->save($this->connection());

        $order->setVersion($order->getVersion() + 1);
        $order->addVersion($this->connection());

        $versions = $this->column('SELECT `quote_id` FROM `order_version` WHERE `id` = '.$order->getId().' ORDER BY `version` DESC');

        self::assertNotEmpty($versions);
        self::assertSame((string) $quote->getId(), $versions[0]);
    }

    public function testACartCarriesTheAcceptedRevisionOfAQuoteAndTheCartSetAsideForIt(): void
    {
        $quote = $this->quote();
        $customer = $quote->getCustomer();
        $setAside = $this->fixtures->cart($customer);

        $quoteCart = $this->fixtures->cart($customer);
        $quoteCart->setQuoteId($quote->getId());
        $quoteCart->setQuoteRevision(3);
        $quoteCart->setSetAsideCartId($setAside->getId());
        $quoteCart->save($this->connection());

        $reloaded = CartQuery::create()->findPk($quoteCart->getId());

        self::assertSame($quote->getId(), $reloaded->getQuote()->getId());
        self::assertSame(3, $reloaded->getQuoteRevision());
        self::assertSame($setAside->getId(), $reloaded->getSetAsideCart()->getId());
        self::assertNull(CartQuery::create()->findPk($setAside->getId())->getQuoteId(), 'An ordinary cart carries no quote.');

        $this->connection()->exec('DELETE FROM `cart` WHERE `id` = '.$setAside->getId());

        self::assertNull(CartQuery::create()->findPk($quoteCart->getId())->getSetAsideCartId(), 'The cart of the quote outlives the cart set aside.');

        $this->connection()->exec('DELETE FROM `quote` WHERE `id` = '.$quote->getId());

        $afterDeletion = CartQuery::create()->findPk($quoteCart->getId());

        self::assertNotNull($afterDeletion);
        self::assertNull($afterDeletion->getQuoteId());
    }

    public function testACartLineCarriesTheLineOfTheQuoteItFreezesThePriceOf(): void
    {
        $quote = $this->quote();
        $item = $this->item($this->revision($quote, 1));
        $product = $this->product();
        $cart = $this->fixtures->cart($quote->getCustomer());

        $line = $this->fixtures->cartItem($cart, $product);
        $line->setQuoteItemId($item->getId());
        $line->save($this->connection());

        self::assertSame($item->getId(), CartItemQuery::create()->findPk($line->getId())->getQuoteItem()->getId());

        $this->connection()->exec('DELETE FROM `quote` WHERE `id` = '.$quote->getId());

        $reloaded = CartItemQuery::create()->findPk($line->getId());

        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getQuoteItemId());
    }

    public function testTheQuoteSettingsAreSeededWithTheDefaultsOfAShopWithNoQuote(): void
    {
        self::assertSame('disabled', ConfigQuery::read('quote_mode'));
        self::assertSame('30', ConfigQuery::read('quote_validity_days'));
        self::assertSame('QUO%08d', ConfigQuery::read('quote_ref_format'));
    }

    public function testTheQuoteSettingsAreDescribedInEveryLanguageOfTheInstall(): void
    {
        foreach (['quote_mode', 'quote_validity_days', 'quote_ref_format'] as $name) {
            $locales = $this->column(\sprintf(
                "SELECT i.`locale` FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` = '%s' ORDER BY i.`locale`",
                $name,
            ));

            self::assertSame(['cs_CZ', 'de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'ru_RU'], $locales, $name);
        }

        self::assertSame(
            'Jours de validité d\'un devis après son envoi (30 si invalide)',
            $this->column("SELECT i.`title` FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` = 'quote_validity_days' AND i.`locale` = 'fr_FR'")[0],
        );
    }

    public function testTheQuoteResourceIsSeededAndNamedInEveryLanguage(): void
    {
        $resource = ResourceQuery::create()->findOneByCode(AdminResources::QUOTE);

        self::assertNotNull($resource);
        self::assertSame(74, $resource->getId());
        self::assertSame('Quotes', $resource->setLocale('en_US')->getTitle());
        self::assertSame('Devis', $resource->setLocale('fr_FR')->getTitle());
        self::assertSame(['8'], $this->column('SELECT COUNT(*) FROM `resource_i18n` WHERE `id` = '.$resource->getId()));
    }

    public function testTheFiveMessagesOfTheQuoteExchangeAreSeededWithTheReferenceInTheirSubject(): void
    {
        $expected = ['quote_requested', 'quote_sent', 'quote_countered', 'quote_accepted', 'quote_rejected'];

        foreach ($expected as $name) {
            $message = MessageQuery::create()->findOneByName($name);

            self::assertNotNull($message, $name);
            self::assertSame($name.'.txt', $message->getTextTemplateFileName());
            self::assertSame($name.'.html', $message->getHtmlTemplateFileName());

            foreach (['en_US', 'fr_FR'] as $locale) {
                $subject = $message->setLocale($locale)->getSubject();

                self::assertStringContainsString('{{ quote_ref }}', (string) $subject, $name.' '.$locale);
            }
        }

        self::assertSame('Votre devis {{ quote_ref }} sur {{ config("store_name") }}', MessageQuery::create()->findOneByName('quote_sent')->setLocale('fr_FR')->getSubject());
    }

    public function testTheRolesThatMayAskForAQuoteAreTheAdministratorAndTheBuyerNotTheApprover(): void
    {
        $granted = [];

        foreach (CompanyRoleQuery::create()->filterByCompanyId(null) as $role) {
            $granted[$role->getCode()] = \in_array('quote.request', $role->getPermissionList(), true);
        }

        self::assertSame(['company_admin' => true, 'buyer' => true, 'approver' => false], $granted);
    }

    private function assertRefusedByTheDatabase(string $statement): void
    {
        try {
            $this->connection()->exec($statement);
        } catch (\PDOException $refusal) {
            self::assertSame('23000', (string) $refusal->getCode(), $refusal->getMessage());

            return;
        }

        self::fail('The database accepted: '.$statement);
    }

    private function connection(): ConnectionInterface
    {
        return $this->getPropelConnection();
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        return array_map(static fn (array $row): string => (string) $row[0], $this->connection()->query($sql)->fetchAll(\PDO::FETCH_NUM));
    }

    private function copyOfAnAddress(QuoteRevision $revision, string $kind): QuoteRevisionAddress
    {
        $copy = (new QuoteRevisionAddress())
            ->setQuoteRevisionId($revision->getId())
            ->setKind($kind)
            ->setFirstname('Ada')
            ->setCity('Lyon');
        $copy->save($this->connection());

        return $copy;
    }

    private function customer(): Customer
    {
        return $this->fixtures->customer($this->fixtures->customerTitle());
    }

    private function product(): Product
    {
        return $this->fixtures->product($this->fixtures->category(), $this->fixtures->taxRule(), $this->fixtures->currency(), ['ref' => 'PROD-A']);
    }

    private function quote(?Customer $customer = null): Quote
    {
        $quote = new Quote();
        $quote->setRef(\sprintf('QUO%08d', 90_000_000 + random_int(0, 9_999_999) + ++$this->sequence));
        $quote->setCustomerId(($customer ?? $this->customer())->getId());
        $quote->setState('requested');
        $quote->setCurrencyId($this->fixtures->currency()->getId());
        $quote->save($this->connection());

        return $quote;
    }

    private function revision(Quote $quote, int $number, ?Admin $admin = null): QuoteRevision
    {
        $revision = new QuoteRevision();
        $revision->setQuoteId($quote->getId());
        $revision->setNumber($number);
        $revision->setAuthor(null === $admin ? 'customer' : 'admin');
        $revision->setAdminId($admin?->getId());
        $revision->setState('draft');
        $revision->save($this->connection());

        return $revision;
    }

    /**
     * @param array{product?: Product, taxRule?: \Thelia\Model\TaxRule} $overrides
     */
    private function item(QuoteRevision $revision, array $overrides = []): QuoteItem
    {
        $item = new QuoteItem();
        $item->setQuoteRevisionId($revision->getId());
        $item->setPosition(1);
        $item->setProductRef('PROD-A');
        $item->setProductSaleElementsRef('PROD-A-1');
        $item->setTitle('Product A');
        $item->setQuantity(12.5);
        $item->setCatalogPrice('100.000000');
        $item->setPrice('90.000000');

        if (isset($overrides['product'])) {
            $item->setProductId($overrides['product']->getId());
            $item->setProductSaleElementsId($overrides['product']->getDefaultSaleElements()->getId());
        }

        if (isset($overrides['taxRule'])) {
            $item->setTaxRuleId($overrides['taxRule']->getId());
            $item->setTaxRuleTitle('VAT 20 %');
        }

        $item->save($this->connection());

        return $item;
    }
}
