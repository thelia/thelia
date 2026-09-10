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
use Thelia\Model\Admin;
use Thelia\Model\Customer;
use Thelia\Model\Tag;
use Thelia\Model\TagElement;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The tag column and the tag filter of the customer list.
 *
 * Ticking a second tag widens the result rather than narrowing it, the way the
 * language and title filters of this same screen behave.
 */
final class CustomerListTagsTest extends WebIntegrationTestCase
{
    private const URL = '/admin/customers';

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen lives in the back-office theme, a separate composer package.
        // Skipping rather than failing when the installed theme predates it.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Service\\Customer\\CustomerFilters')) {
            self::markTestSkipped('The installed back-office theme has no customer list filters.');
        }

        if (!property_exists('BackOfficeDefaultTwigBundle\\Service\\Customer\\CustomerFilters', 'tagIds')) {
            self::markTestSkipped('The installed back-office theme has no customer tag filter.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
    }

    protected function tearDown(): void
    {
        // Nullable and guarded: tearDown() still runs after setUp() skipped the
        // test, and the injector was never built in that case.
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testTheListShowsTheTagsOfEachCustomer(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'List shown', 'colorCode' => '#1A2B3C']);
        $customer = $this->taggedCustomer($factory, $tag, 'Shown');

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?q='.urlencode((string) $customer->getRef()));

        // Scoped to the cell, never to the whole page: the filter offers every
        // tag with its colour, so a page-wide assertion passes with no column
        // at all — it did, until a sabotage said so.
        $cell = $this->tagCellHtml();
        self::assertStringContainsString('List shown', $cell);
        self::assertStringContainsString('#1A2B3C', $cell, 'The colour is rendered as a swatch.');
    }

    /**
     * A stored colour that is not a colour must not reach the style attribute:
     * a row written by an import or a hand-run statement never passed the API
     * validator, and the cell is built as raw HTML.
     *
     * Seven characters on purpose, the width of the column: a longer payload is
     * refused by the column itself, which proves nothing about this guard.
     */
    public function testAStoredColourThatIsNotAColourIsNotRendered(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'List injected']);
        $tag->setColorCode('#GGGGGG')->save($this->getPropelConnection());
        $customer = $this->taggedCustomer($factory, $tag, 'Injected');

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?q='.urlencode((string) $customer->getRef()));

        $cell = $this->tagCellHtml();
        self::assertStringContainsString('List injected', $cell, 'The tag itself is still listed.');
        self::assertStringNotContainsString('#GGGGGG', $cell, 'A value that is not a colour never reaches the style attribute.');
    }

    public function testTheFilterKeepsOnlyTheCustomersCarryingTheTag(): void
    {
        $factory = $this->factory();
        $wanted = $factory->tag(['label' => 'List wanted']);
        $carrier = $this->taggedCustomer($factory, $wanted, 'Carrier');
        $other = $factory->customer($factory->customerTitle(), ['lastname' => 'ListOutsider']);

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?tag_ids[]='.$wanted->getId());

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString((string) $carrier->getRef(), $html);
        self::assertStringNotContainsString((string) $other->getRef(), $html, 'A customer carrying nothing is filtered out.');
    }

    /**
     * The decision recorded for this filter: ticking a second tag widens the
     * result. A customer carrying only the second one has to come back.
     */
    public function testTwoTagsWidenTheResultInsteadOfNarrowingIt(): void
    {
        $factory = $this->factory();
        $first = $factory->tag(['label' => 'List first']);
        $second = $factory->tag(['label' => 'List second']);
        $onlyFirst = $this->taggedCustomer($factory, $first, 'OnlyFirst');
        $onlySecond = $this->taggedCustomer($factory, $second, 'OnlySecond');

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?tag_ids[]='.$first->getId().'&tag_ids[]='.$second->getId());

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString((string) $onlyFirst->getRef(), $html);
        self::assertStringContainsString((string) $onlySecond->getRef(), $html, 'Ticking a second tag must not require both.');
    }

    /**
     * A customer carrying both tags appears once. A join instead of a correlated
     * EXISTS would return it twice, and the page would count it twice.
     */
    public function testACustomerCarryingBothTagsIsListedOnce(): void
    {
        $factory = $this->factory();
        $first = $factory->tag(['label' => 'List both one']);
        $second = $factory->tag(['label' => 'List both two']);
        $customer = $this->taggedCustomer($factory, $first, 'Both');
        $factory->tagElement($second, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?tag_ids[]='.$first->getId().'&tag_ids[]='.$second->getId());

        // Counted on the table rows, not on the reference links: the DataTable
        // repeats hidden columns in a details row for small breakpoints, so the
        // link appears twice for a single result.
        $rows = $this->client->getCrawler()->filter('[data-testid="datatable-customers-row"]')->count();

        self::assertSame(
            1,
            $rows,
            'A correlated EXISTS returns the customer once whatever the number of tags it carries.',
        );
    }

    /**
     * The tag clause must not leak into the surrounding WHERE. Chaining _or()
     * on a Propel query does exactly that, turning the whole clause into an OR;
     * three customers tell an AND apart from that leak.
     */
    public function testTheTagFilterCombinesWithAnotherFilterAsAnAnd(): void
    {
        $factory = $this->factory();
        $title = $factory->customerTitle();
        $tag = $factory->tag(['label' => 'List combined']);

        $taggedAccount = $this->taggedCustomer($factory, $tag, 'CombinedMatch');
        $untaggedAccount = $factory->customer($title, ['lastname' => 'CombinedUntagged']);
        $taggedGuest = $factory->guestCustomer($title, ['lastname' => 'CombinedGuest']);
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $taggedGuest->getId());

        $this->loginAs($factory->admin());
        // 'without' and not '0': the tri-state parser of this screen reads the
        // words, and an unknown value silently means "no filter at all", which
        // would leave this test asserting nothing. Spelled out rather than
        // imported: this test lives in core and must not depend on a theme class.
        $this->assertPageRenders(self::URL.'?tag_ids[]='.$tag->getId().'&guest=without');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('CombinedMatch', $html, 'Tagged and not a guest: the only match.');
        self::assertStringNotContainsString('CombinedUntagged', $html, 'Not a guest but untagged.');
        self::assertStringNotContainsString('CombinedGuest', $html, 'Tagged but a guest.');
    }

    public function testAnUnknownTagIdentifierMatchesNobody(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'List unknown']);
        $carrier = $this->taggedCustomer($factory, $tag, 'Unknown');

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL.'?tag_ids[]=999999');

        self::assertStringNotContainsString(
            (string) $carrier->getRef(),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testTheFilterOffersTheWholeVocabulary(): void
    {
        $factory = $this->factory();
        $factory->tag(['label' => 'List offered but unused']);

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL);

        self::assertStringContainsString(
            'List offered but unused',
            (string) $this->client->getResponse()->getContent(),
            'A tag nobody carries is still offered, so filtering on it answers an empty list.',
        );
    }

    /**
     * The tag cell of the single row the filtered page holds.
     */
    private function tagCellHtml(): string
    {
        $cells = $this->client->getCrawler()->filter('[data-testid="bo-customer-tags"]');
        self::assertGreaterThan(0, $cells->count(), 'The list renders a tag cell.');

        return $cells->first()->html();
    }

    private function taggedCustomer(FixtureFactory $factory, Tag $tag, string $lastname): Customer
    {
        $customer = $factory->customer($factory->customerTitle(), ['lastname' => $lastname]);
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        return $customer;
    }

    private function factory(): FixtureFactory
    {
        // Deliberately not createFixtureFactory(): that helper pushes a synthetic
        // request when the stack is empty, which would then be the "main" request
        // the security context reads its session from.
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
