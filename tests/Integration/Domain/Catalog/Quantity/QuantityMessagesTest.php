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

namespace Thelia\Tests\Integration\Domain\Catalog\Quantity;

use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Quantity\NotPositiveQuantityException;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The buyer reads why a quantity is refused or adjusted in the language of the shop,
 * with the numbers written the way that language writes them.
 */
final class QuantityMessagesTest extends IntegrationTestCase
{
    private Session $session;

    private mixed $previousLang;

    protected function setUp(): void
    {
        parent::setUp();

        // The messages follow the language of the shop session, as on a storefront.
        $session = $this->getService('request_stack')->getMainRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);
        $this->session = $session;
        $this->previousLang = $session->get('thelia.current.lang');
        $session->setLang(LangQuery::create()->findOneByLocale('fr_FR') ?? throw new \RuntimeException('No French language.'));
    }

    protected function tearDown(): void
    {
        $this->session->set('thelia.current.lang', $this->previousLang);

        parent::tearDown();
    }

    public function testSevenIsRefusedForACombinationSoldBySix(): void
    {
        self::assertSame('Vendu par 6 : 6, 12, 18…', $this->refusalOf(7, new QuantityRule(minQuantity: 2, step: 6)));
    }

    public function testTheValidQuantitiesByMeasureAreWrittenWithADecimalComma(): void
    {
        self::assertSame(
            'Vendu par 0,25 : 1,00 ; 1,25 ; 1,50…',
            $this->refusalOf(1.3, new QuantityRule(minQuantity: 1, step: 0.25, decimals: 2)),
        );
    }

    public function testTheOtherRefusalsAreTranslated(): void
    {
        self::assertSame('La plus petite quantité que vous pouvez commander est 6.', $this->refusalOf(1, new QuantityRule(minQuantity: 2, step: 6)));
        self::assertSame('La quantité doit être un nombre entier.', $this->refusalOf(1.5, new QuantityRule()));
        self::assertSame(
            'La quantité ne peut pas avoir plus de 2 décimale(s).',
            $this->refusalOf(1.255, new QuantityRule(minQuantity: 1, step: 0.25, decimals: 2)),
        );

        try {
            (new QuantityPolicy())->assertAcceptable(0, new QuantityRule());
            self::fail('Zero must be refused.');
        } catch (NotPositiveQuantityException $exception) {
            self::assertSame('La quantité doit être supérieure à zéro.', $exception->getMessage());
        }
    }

    public function testTheAdjustmentOfALineReadBackIsTranslated(): void
    {
        $policy = new QuantityPolicy();

        self::assertSame('La quantité a été ramenée à 6 : vendu par 6.', $policy->normalize(7, new QuantityRule(minQuantity: 2, step: 6))->message());
        self::assertSame('La quantité a été ramenée à 1,25.', $policy->normalize(1.255, new QuantityRule(step: 0.25, decimals: 2))->message());
        self::assertSame(
            'Cette ligne a été retirée : la plus petite quantité que vous pouvez commander est 6.',
            $policy->normalize(1, new QuantityRule(minQuantity: 2, step: 6))->message(),
        );
    }

    private function refusalOf(float $requested, QuantityRule $rule): string
    {
        try {
            (new QuantityPolicy())->assertAcceptable($requested, $rule);
        } catch (QuantityRefusedException $refusal) {
            return $refusal->getMessage();
        }

        self::fail('The quantity must be refused.');
    }
}
