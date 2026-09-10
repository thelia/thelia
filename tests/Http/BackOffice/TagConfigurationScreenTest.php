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
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\Tag;
use Thelia\Model\TagElement;
use Thelia\Model\TagElementQuery;
use Thelia\Model\TagQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The tag vocabulary screen of the configuration.
 *
 * Guarded by its own admin resource: a profile allowed to rename or delete a tag
 * reaches every customer carrying it, which is a wider reach than editing one
 * customer, so the customer resource is deliberately not enough.
 */
final class TagConfigurationScreenTest extends WebIntegrationTestCase
{
    private const URL = '/admin/configuration/tags';

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen lives in the back-office theme, a separate composer package.
        // Skipping rather than failing when the installed theme predates it: a
        // core test that hard-requires unreleased theme code turns this suite red
        // for a reason that has nothing to do with core, which is exactly how the
        // sale-targeting tests broke.
        if (!class_exists('BackOfficeDefaultTwigBundle\\Controller\\Configuration\\TagController')) {
            self::markTestSkipped('The installed back-office theme has no tag configuration screen.');
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

    public function testTheScreenIsServedToAnAdminHoldingTheTagResource(): void
    {
        $this->loginAs($this->factory()->admin());

        $this->assertPageRenders(self::URL);
    }

    public function testTheScreenListsTheTagsWithTheirCustomerCount(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Screen VIP', 'colorCode' => '#1A2B3C']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Screen VIP', $html);
        self::assertStringContainsString('#1A2B3C', $html, 'The colour is rendered as a swatch.');
    }

    /**
     * A stored colour that is not a colour must not reach the style attribute.
     * The screen re-checks on the way out, because a row written by an import or
     * a hand-run SQL statement never passed the API validator.
     *
     * The value is seven characters long on purpose, the width of the column: a
     * longer payload is refused by the column itself, which proves nothing about
     * the guard this test is here for.
     */
    public function testAStoredColourThatIsNotAColourIsNotRendered(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Screen injected']);
        $tag->setColorCode('#GGGGGG')->save($this->getPropelConnection());

        $this->loginAs($factory->admin());
        $this->assertPageRenders(self::URL);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Screen injected', $html, 'The tag itself is still listed.');
        self::assertStringNotContainsString('#GGGGGG', $html, 'A value that is not a colour never reaches the style attribute.');
    }

    public function testAnAdminWithoutTheTagResourceIsRefused(): void
    {
        $customerOnlyAdmin = $this->factory()->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE],
        ]);
        $this->loginAs($customerOnlyAdmin);

        $this->client->request('GET', self::URL);

        self::assertSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'Being allowed on customers must not open the tag vocabulary.',
        );
    }

    public function testRenamingATagChangesItsLabelAndColour(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Rename me', 'colorCode' => '#111111']);
        $this->loginAs($factory->admin());

        $this->submitEditForm($tag, ['label' => 'Renamed', 'colorCode' => '#22ccff']);

        $reloaded = TagQuery::create()->findPk($tag->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Renamed', $reloaded->getLabel());
        self::assertSame('#22ccff', $reloaded->getColorCode());
    }

    /**
     * The refusal has to name the tag standing in the way: under
     * utf8mb4_general_ci the collision lands on a spelling that does not look
     * like the target, so a bare "already exists" leaves nothing to act on.
     */
    public function testRenamingOntoAnExistingLabelIsRefusedAndNamesTheOtherTag(): void
    {
        $factory = $this->factory();
        $existing = $factory->tag(['label' => 'Salon']);
        $renamed = $factory->tag(['label' => 'Prospect']);
        $this->loginAs($factory->admin());

        $this->submitEditForm($renamed, ['label' => 'Salón']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('Prospect', TagQuery::create()->findPk($renamed->getId())?->getLabel(), 'The tag is untouched.');
        self::assertSame('Salon', TagQuery::create()->findPk($existing->getId())?->getLabel());
    }

    public function testALabelOfOnlyWhitespaceIsRefusedByTheForm(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Keep me']);
        $this->loginAs($factory->admin());

        $this->submitEditForm($tag, ['label' => '   ']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('Keep me', TagQuery::create()->findPk($tag->getId())?->getLabel());
    }

    public function testAnAdminWithoutTheTagResourceCannotOpenTheEditScreen(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag();
        $this->loginAs($factory->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));

        $this->client->request('GET', self::URL.'/update?tag_id='.$tag->getId());

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * A native colour input has no empty state and posts black by default, so a
     * tag with no colour would turn black on its first save. The checkbox is
     * what keeps "no colour" expressible.
     */
    public function testATagWithoutAColourKeepsNoneWhenSavedUntouched(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Colourless', 'colorCode' => null]);
        $this->loginAs($factory->admin());

        // Black is posted on purpose: a real browser sends #000000 for a colour
        // input left alone, while BrowserKit would send the empty rendered value.
        // Without it this test would pass on a submission no browser ever makes,
        // and would miss exactly the regression it exists for.
        $this->submitEditForm($tag, ['label' => 'Colourless renamed', 'colorCode' => '#000000', 'noColor' => '1']);

        $reloaded = TagQuery::create()->findPk($tag->getId());
        self::assertSame('Colourless renamed', $reloaded?->getLabel());
        self::assertNull($reloaded?->getColorCode(), 'Saving must not invent a black colour.');
    }

    public function testAColourCanBeTakenBackOff(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Was coloured', 'colorCode' => '#1A2B3C']);
        $this->loginAs($factory->admin());

        $this->submitEditForm($tag, ['noColor' => '1']);

        self::assertNull(TagQuery::create()->findPk($tag->getId())?->getColorCode());
    }

    public function testTickingNoColourWinsOverThePickedValue(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Ambiguous', 'colorCode' => '#1A2B3C']);
        $this->loginAs($factory->admin());

        $this->submitEditForm($tag, ['colorCode' => '#ff0000', 'noColor' => '1']);

        self::assertNull(TagQuery::create()->findPk($tag->getId())?->getColorCode(), 'The checkbox is the explicit intent.');
    }

    /**
     * Goes through the rendered form rather than posting fields by hand, so the
     * CSRF token travels with the submission the way a browser sends it.
     *
     * @param array<string, string> $values
     */
    private function submitEditForm(Tag $tag, array $values): void
    {
        // assertPageRenders rather than a blanket "skip unless 200": that helper
        // skips only when the theme assets are missing, and asserts otherwise. A
        // bare status check turns any server error into a skip, which is how a
        // broken screen can masquerade as a passing suite.
        $this->assertPageRenders(self::URL.'/update?tag_id='.$tag->getId());

        $form = $this->client->getCrawler()->filter('form[action$="/tags/save"]')->form();

        foreach ($values as $field => $value) {
            $form['thelia_tag_update['.$field.']'] = $value;
        }

        // The fixture factory gives every tag a colour, so a form rendered for a
        // colourless tag arrives with the checkbox ticked: untick it whenever the
        // case under test posts a colour.
        if (isset($values['colorCode']) && !isset($values['noColor'])) {
            $form['thelia_tag_update[noColor]'] = false;
        }

        $this->client->submit($form);
    }

    public function testDeletingATagTakesItOffEveryCustomer(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Delete me']);
        $customer = $factory->customer($factory->customerTitle());
        $factory->tagElement($tag, TagElement::ELEMENT_KEY_CUSTOMER, $customer->getId());
        $tagId = (int) $tag->getId();
        $this->loginAs($factory->admin());

        $this->submitDeleteDialog($tagId);

        self::assertNull(TagQuery::create()->findPk($tagId));
        self::assertSame(0, TagElementQuery::create()->filterByTagId($tagId)->count(), 'The cascade takes the attachments with it.');
    }

    /**
     * Deleting a tag strips it from every customer at once, so an unguarded GET
     * would be a one-click forgery. The token is the guard.
     */
    public function testDeletingWithoutTheTokenIsRefused(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Survive']);
        $tagId = (int) $tag->getId();
        $this->loginAs($factory->admin());

        $this->client->request('GET', self::URL.'/delete?tag_id='.$tagId);

        self::assertNotNull(TagQuery::create()->findPk($tagId), 'A request with no token must not delete anything.');
    }

    public function testAnAdminWithoutTheTagResourceCannotDelete(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag();
        $tagId = (int) $tag->getId();
        // DELETE is granted on customers on purpose: without it the refusal would
        // prove nothing about the tag resource, since a profile holding no delete
        // right anywhere is refused whichever resource the screen checks.
        $this->loginAs($factory->restrictedAdmin([
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE, AccessManager::DELETE],
        ]));

        $this->client->request('GET', self::URL.'/delete?tag_id='.$tagId);

        // 403 and not merely "the tag survived": a missing token also spares the
        // tag, but answers a redirect. Asserting the status is what tells the two
        // guards apart, and so what proves this screen checks the tag resource
        // rather than the customer one.
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertNotNull(TagQuery::create()->findPk($tagId));
    }

    /**
     * Submits the confirmation dialog of the list rather than crafting the
     * request: the token the dialog carries in its action URL travels with it,
     * the way it does for an administrator clicking Delete.
     */
    private function submitDeleteDialog(int $tagId): void
    {
        $this->assertPageRenders(self::URL);

        $form = $this->client->getCrawler()->filter('form[action*="/tags/delete"]')->form();
        $form['tag_id'] = (string) $tagId;

        $this->client->submit($form);
    }

    /**
     * Three customers on purpose: one carrying only the absorbed tag, one only
     * the surviving one, one carrying both. The customer carrying both must come
     * out with a single attachment, and the announced count must be three, not
     * the four the two counts add up to.
     */
    public function testMergingMovesTheAttachmentsAndDropsTheAbsorbedTag(): void
    {
        $factory = $this->factory();
        $absorbed = $factory->tag(['label' => 'Absorbed']);
        $surviving = $factory->tag(['label' => 'Surviving']);
        $onlyAbsorbed = $factory->customer($factory->customerTitle());
        $onlySurviving = $factory->customer($factory->customerTitle());
        $both = $factory->customer($factory->customerTitle());
        $factory->tagElement($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $onlyAbsorbed->getId());
        $factory->tagElement($surviving, TagElement::ELEMENT_KEY_CUSTOMER, $onlySurviving->getId());
        $factory->tagElement($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $factory->tagElement($surviving, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $absorbedId = (int) $absorbed->getId();
        $survivingId = (int) $surviving->getId();
        $this->loginAs($factory->admin());

        $this->confirmMerge($absorbedId, $survivingId);

        self::assertNull(TagQuery::create()->findPk($absorbedId), 'The absorbed tag is gone.');
        self::assertSame(3, TagElementQuery::create()->filterByTagId($survivingId)->count(), 'Three customers, and the one carrying both is not duplicated.');
    }

    public function testTheConfirmationAnnouncesTheResultingCountAndNotTheSum(): void
    {
        $factory = $this->factory();
        $absorbed = $factory->tag(['label' => 'Absorbed']);
        $surviving = $factory->tag(['label' => 'Surviving']);
        $both = $factory->customer($factory->customerTitle());
        $onlyAbsorbed = $factory->customer($factory->customerTitle());
        $factory->tagElement($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $factory->tagElement($surviving, TagElement::ELEMENT_KEY_CUSTOMER, $both->getId());
        $factory->tagElement($absorbed, TagElement::ELEMENT_KEY_CUSTOMER, $onlyAbsorbed->getId());
        $this->loginAs($factory->admin());

        $this->assertPageRenders(self::URL.'/merge?tag_id='.$absorbed->getId().'&into='.$surviving->getId());
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('afterwards: 2', $html, 'Two customers, not the three the counts add up to.');
    }

    public function testMergingWithoutTheTokenIsRefused(): void
    {
        $factory = $this->factory();
        $absorbed = $factory->tag(['label' => 'Absorbed']);
        $surviving = $factory->tag(['label' => 'Surviving']);
        $absorbedId = (int) $absorbed->getId();
        $this->loginAs($factory->admin());

        $this->client->request('POST', self::URL.'/merge', ['tag_id' => $absorbedId, 'into' => $surviving->getId()]);

        self::assertNotNull(TagQuery::create()->findPk($absorbedId), 'An unconfirmed merge must not touch anything.');
    }

    public function testTheScreenNeverOffersATagAsItsOwnMergeTarget(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Alone with others']);
        $other = $factory->tag(['label' => 'Another one']);
        $this->loginAs($factory->admin());

        $this->assertPageRenders(self::URL.'/update?tag_id='.$tag->getId());

        $options = $this->client->getCrawler()->filter('select[name="into"] option')->extract(['value']);
        self::assertNotContains((string) $tag->getId(), $options, 'A tag cannot absorb itself, so it must not be offered.');
        self::assertContains((string) $other->getId(), $options, 'Every other tag is a legitimate target.');
    }

    public function testMergingATagIntoItselfLeadsNowhere(): void
    {
        $factory = $this->factory();
        $tag = $factory->tag(['label' => 'Itself']);
        $tagId = (int) $tag->getId();
        $this->loginAs($factory->admin());

        $this->client->request('GET', self::URL.'/merge?tag_id='.$tagId.'&into='.$tagId);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertNotNull(TagQuery::create()->findPk($tagId));
    }

    /**
     * Walks the confirmation page and submits its form, so the token travels the
     * way it does for an administrator who clicked through.
     */
    private function confirmMerge(int $absorbedId, int $survivingId): void
    {
        $this->assertPageRenders(self::URL.'/merge?tag_id='.$absorbedId.'&into='.$survivingId);

        $form = $this->client->getCrawler()->filter('form[data-testid="tag-merge-form"]')->form();
        $this->client->submit($form);
    }

    private function factory(): FixtureFactory
    {
        // Deliberately not createFixtureFactory(): that helper pushes a synthetic
        // request when the stack is empty, which would then be the "main" request
        // the security context reads its session from. The admin would land in a
        // session nobody looks at and every page would answer a redirect.
        return new FixtureFactory($this->getPropelConnection());
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
