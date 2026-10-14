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

namespace Thelia\Tests\Integration\Api;

use ApiPlatform\Metadata\Post;
use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Api\Resource\Cart as CartResource;
use Thelia\Api\State\Processor\CartCreationProcessor;
use Thelia\Model\CartQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The cart POST /api/front/carts creates when the body does not name the discount.
 *
 * The resource used to carry a null discount into the INSERT. The `cart`.`discount` column of a
 * shop updated from Thelia 2 is NOT NULL, where the one of a fresh install accepts NULL: on the
 * first the call answered 500 ("Column 'discount' cannot be null"), on the second the cart was
 * stored without discount instead of at zero.
 *
 * The case with the updated column changes the schema, which commits on its own: the transaction
 * of the test is left, and the column gets its fresh install definition back afterwards.
 */
final class CartCreationDiscountTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();

        if ($connection->inTransaction()) {
            $connection instanceof ConnectionWrapper ? $connection->forceRollBack() : $connection->rollBack();
        }

        $connection->exec('ALTER TABLE `cart` MODIFY `discount` DECIMAL(16,6) DEFAULT 0.000000');

        parent::tearDown();
    }

    public function testACartCreatedWithoutDiscountIsStoredAtZero(): void
    {
        $this->getPropelConnection()->beginTransaction();

        $discount = $this->createCartThroughTheProcessor();

        self::assertNotNull($discount, 'The discount of a new cart is zero, not NULL.');
        self::assertEqualsWithDelta(0.0, (float) $discount, 0.0001);
    }

    public function testACartCanBeCreatedOnAShopWhoseDiscountColumnIsNotNull(): void
    {
        $this->getPropelConnection()->exec('ALTER TABLE `cart` MODIFY `discount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL');

        self::assertEqualsWithDelta(0.0, (float) $this->createCartThroughTheProcessor(), 0.0001);
    }

    private function createCartThroughTheProcessor(): ?string
    {
        $cart = $this->getService(CartCreationProcessor::class)->process(
            new CartResource(),
            new Post(denormalizationContext: ['groups' => [CartResource::GROUP_FRONT_WRITE]]),
        );

        self::assertInstanceOf(CartResource::class, $cart);
        self::assertNotNull(CartQuery::create()->findPk($cart->getId(), $this->getPropelConnection()));

        $discount = $this->getPropelConnection()->query('SELECT `discount` FROM `cart` WHERE `id` = '.(int) $cart->getId())->fetchColumn();

        return false === $discount || null === $discount ? null : (string) $discount;
    }
}
