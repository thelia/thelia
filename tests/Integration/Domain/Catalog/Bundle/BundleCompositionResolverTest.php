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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;
use Thelia\Model\LangQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class BundleCompositionResolverTest extends ActionIntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    private BundleCompositionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->resolver = $this->getService(BundleCompositionResolver::class);
    }

    public function testNoCompositionResolvesToTheDefaultOne(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $composition = $this->resolver->resolve((int) $example['bundle']->getId());

        self::assertSame(
            \sprintf('[{"bundleItemId":%d,"quantity":"1"},{"bundleItemId":%d,"quantity":"2"}]', $example['bike']->getId(), $example['helmet']->getId()),
            $composition->toCanonicalJson(),
        );
    }

    public function testAValidChoiceIsKeptAndItsOrderDoesNotMatter(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $proposed = BundleComposition::fromArray([
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
        ]);

        $composition = $this->resolver->resolve((int) $example['bundle']->getId(), $proposed);

        self::assertSame(
            \sprintf('[{"bundleItemId":%d,"quantity":"1"},{"bundleItemId":%d,"quantity":"2"},{"bundleItemId":%d,"quantity":"2"}]', $example['bike']->getId(), $example['helmet']->getId(), $example['lock']->getId()),
            $composition->toCanonicalJson(),
        );
    }

    public function testAnItemOfAnotherBundleIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $other = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('A component chosen is not part of this bundle.');

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $other['lock']->getId(), 'quantity' => '1'],
        ]));
    }

    public function testAQuantityAboveTheMaximumIsRefused(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('Choose between');

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => '4'],
        ]));
    }

    public function testAFixedQuantityCannotBeChanged(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('Choose between');

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
        ]));
    }

    public function testARequiredItemCannotBeLeftOut(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('is required in this bundle');

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
        ]));
    }

    public function testTheRefusalIsASentenceForTheBuyerInTheLanguageOfTheShop(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        // The language of the visitor, as the shop keeps it in the session of the request.
        $session = $this->getService('request_stack')->getMainRequest()?->getSession();
        self::assertNotNull($session);
        $language = $session->getLang();
        $session->setLang(LangQuery::create()->findOneByLocale('fr_FR'));

        try {
            $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
                ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
            ]));
            self::fail('The helmet is required.');
        } catch (InvalidBundleCompositionException $refusal) {
            self::assertSame(\sprintf('Le produit %s est obligatoire dans ce pack.', $example['helmetProduct']->getRef()), $refusal->getMessage());
            self::assertSame((int) $example['helmet']->getId(), $refusal->bundleItemId, 'The id stays on the exception, out of the sentence.');
        } finally {
            $session->setLang($language);
        }
    }

    public function testAnOptionalItemMayBeLeftOut(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $composition = $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => '0'],
        ]));

        self::assertSame('0', $composition->quantityOf((int) $example['lock']->getId()));
    }

    public function testAnOfflineComponentIsNotSellable(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['lockProduct'])->setVisible(false)->save();

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('cannot be sold');

        $this->resolver->resolve((int) $example['bundle']->getId(), BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => '1'],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => '2'],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => '1'],
        ]));
    }

    public function testAnOfflineOptionalComponentLeftOutDoesNotBlockTheBundle(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $example['lockProduct']->setVisible(0)->save();

        $composition = $this->resolver->resolve((int) $example['bundle']->getId());

        self::assertSame('0', $composition->quantityOf((int) $example['lock']->getId()));
    }

    public function testAProductThatIsNotABundleHasNoComposition(): void
    {
        $plain = $this->component($this->factory, '10', 1);

        $this->expectException(InvalidBundleCompositionException::class);
        $this->expectExceptionMessage('This product is sold on its own');

        $this->resolver->resolve((int) $plain->getId());
    }
}
