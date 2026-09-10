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
use Thelia\Model\TagElement;
use Thelia\Model\TagQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Tagging one customer from its own screen.
 *
 * Guarded by the customer resource and not by the tag one: putting a marker on a
 * customer reaches that customer alone, unlike renaming or merging a tag, which
 * reaches everyone carrying it.
 */
final class CustomerTagsScreenTest extends WebIntegrationTestCase
{
    private const EDIT_URL = '/admin/customer/update?customer_id=';
    private const FORM_NAME = 'thelia_customer_update';

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen lives in the back-office theme, a separate composer package.
        // Skipping rather than failing when the installed theme predates it: a
        // core test that hard-requires unreleased theme code turns this suite red
        // for a reason that has nothing to do with core.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\Customer\\CustomerController')) {
            self::markTestSkipped('The installed back-office theme has no customer screen.');
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

    public function testTheScreenOffersTheKnownTagsAndTicksTheOnesTheCustomerCarries(): void
    {
        $factory = $this->factory();
        $carried = $factory->tag(['label' => 'Sheet carried']);
        $factory->tag(['label' => 'Sheet offered']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($carried, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::EDIT_URL.$customer->getId());

        $options = $this->client->getCrawler()->filter('[data-testid="customer-tags-select"] option');
        $offered = [];
        $selected = [];
        foreach ($options as $option) {
            $label = trim((string) $option->textContent);
            $offered[] = $label;
            if ($option->hasAttribute('selected')) {
                $selected[] = $label;
            }
        }

        self::assertContains('Sheet carried', $offered);
        self::assertContains('Sheet offered', $offered, 'The whole vocabulary is offered, not only what is carried.');
        self::assertSame(['Sheet carried'], $selected);
    }

    public function testPickingAKnownTagAttachesIt(): void
    {
        $factory = $this->factory();
        $factory->tag(['label' => 'Sheet pick me']);
        $customer = $factory->customer($factory->customerTitle());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($customer, ['Sheet pick me'], '');

        self::assertSame(['Sheet pick me'], $this->carriedLabels($customer));
    }

    public function testATagTypedOnTheSheetIsCreatedAndAttached(): void
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($customer, [], 'Sheet brand new');

        self::assertSame(['Sheet brand new'], $this->carriedLabels($customer));
        self::assertNotNull(TagQuery::create()->findOneByLabel('Sheet brand new'), 'The vocabulary learns the tag.');
    }

    public function testSeveralTypedTagsAreSplitOnCommas(): void
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($customer, [], 'Sheet first, Sheet second');

        self::assertSame(['Sheet first', 'Sheet second'], $this->carriedLabels($customer));
    }

    public function testUnpickingATagTakesItOffTheCustomerWithoutDeletingIt(): void
    {
        $factory = $this->factory();
        $dropped = $factory->tag(['label' => 'Sheet dropped']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($dropped, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($customer, [], '');

        self::assertSame([], $this->carriedLabels($customer));
        self::assertNotNull(TagQuery::create()->findPk($dropped->getId()), 'The vocabulary keeps the tag for other customers.');
    }

    /**
     * The tags of one customer are its own: writing them must not touch the
     * neighbour, which is what a missing element identifier would do.
     */
    public function testSavingOneCustomerLeavesTheTagsOfAnotherAlone(): void
    {
        $factory = $this->factory();
        $shared = $factory->tag(['label' => 'Sheet shared']);
        $title = $factory->customerTitle();
        $edited = $factory->customer($title);
        $neighbour = $factory->customer($title);
        $factory->tagElement($shared, TagElement::ELEMENT_KEY_CUSTOMER, $neighbour->getId());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($edited, [], 'Sheet only mine');

        self::assertSame(['Sheet only mine'], $this->carriedLabels($edited));
        self::assertSame(['Sheet shared'], $this->carriedLabels($neighbour));
    }

    /**
     * The realistic regression: an operator edits a phone number and saves. The
     * screen posts whatever it rendered, so a form that failed to pre-tick the
     * carried tags silently strips them.
     */
    public function testSavingWithoutTouchingTheTagsKeepsThem(): void
    {
        $factory = $this->factory();
        $kept = $factory->tag(['label' => 'Sheet untouched']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($kept, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $this->loginAs($factory->admin());

        $this->assertPageRenders(self::EDIT_URL.$customer->getId());
        $form = $this->client->getCrawler()->filter('form[action$="/customer/save"]')->form();
        $this->client->submit($form);

        self::assertSame(['Sheet untouched'], $this->carriedLabels($customer));
    }

    public function testATypedLabelOfOnlyWhitespaceCreatesNothing(): void
    {
        $factory = $this->factory();
        $customer = $factory->customer($factory->customerTitle());
        $this->loginAs($factory->admin());

        $this->submitCustomerForm($customer, [], '   ,  ');

        self::assertSame([], $this->carriedLabels($customer));
        self::assertNull(TagQuery::create()->findOneByLabel(''), 'A blank label never becomes a tag.');
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

    /**
     * @return list<string>
     */
    private function carriedLabels(Customer $customer): array
    {
        $labels = [];

        foreach (TagQuery::create()
            ->useTagElementQuery()
                ->filterByElementKey(TagElement::ELEMENT_KEY_CUSTOMER)
                ->filterByElementId((int) $customer->getId())
            ->endUse()
            ->orderByLabel()
            ->find() as $tag) {
            $labels[] = (string) $tag->getLabel();
        }

        return $labels;
    }

    /**
     * Goes through the rendered form so the CSRF token and every other required
     * field travel the way a browser sends them.
     *
     * @param list<string> $pickedLabels
     */
    private function submitCustomerForm(Customer $customer, array $pickedLabels, string $typedLabels): void
    {
        // assertPageRenders rather than a blanket "skip unless 200": that helper
        // skips only when the theme assets are missing, and asserts otherwise.
        $this->assertPageRenders(self::EDIT_URL.$customer->getId());

        $form = $this->client->getCrawler()->filter('form[action$="/customer/save"]')->form();
        $form[self::FORM_NAME.'[tags]'] = $pickedLabels;
        $form[self::FORM_NAME.'[new_tags]'] = $typedLabels;

        $this->client->submit($form);
    }
}
