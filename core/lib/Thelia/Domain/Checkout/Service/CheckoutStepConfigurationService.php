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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Checkout\Exception\CheckoutStepConfigurationException;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;
use Thelia\Model\Map\CheckoutStepTableMap;

/**
 * The merchant's side of the checkout steps: which ones are asked for, and in which
 * order.
 *
 * A tunnel has a shape a shop cannot sell without — it opens on the cart, it takes the
 * money next to last and it ends on the confirmation — so those three moves are refused
 * here rather than left for the front office to cope with. Everything in between is the
 * merchant's to reorder and to turn off. The rule itself is CheckoutTunnelShape's, and
 * it is the one the progression repairs a hand-written table with: a configuration
 * refused here is exactly the one repaired there.
 *
 * Turning a step off removes its screen and nothing else: CheckoutValidationService runs
 * the check of every step the installed code declares when the order is placed, and
 * never reads this table to decide which. A step of a module is no exception — shipping
 * the provider is all it takes for its refusal to hold at placement.
 */
final readonly class CheckoutStepConfigurationService
{
    /**
     * @param iterable<CheckoutStepProviderInterface> $stepProviders
     */
    public function __construct(
        #[AutowireIterator('thelia.checkout.step_provider')]
        private iterable $stepProviders,
        private CheckoutTunnelShape $tunnelShape,
    ) {
    }

    /**
     * Turns a step on or off. A mandatory one is refused: a checkout with no payment
     * screen is not a shorter tunnel, it is one nobody can buy through.
     *
     * @throws CheckoutStepConfigurationException when the step may not be turned off
     * @throws PropelException
     */
    public function setActive(string $code, bool $active): CheckoutStep
    {
        $step = $this->step($code);

        if (!$active && $step->isMandatory()) {
            throw new CheckoutStepConfigurationException('The checkout step "%code" is required by the shop and cannot be turned off.', parameters: ['%code' => $code]);
        }

        $step->setActive($active ? 1 : 0)->save();

        return $step;
    }

    /**
     * Moves a step to that place in the tunnel, counting from 1, and renumbers the list
     * so the positions stay contiguous.
     *
     * The whole list is judged after the move, not the step alone: moving anything
     * ahead of the cart or past the payment breaks the tunnel just as much as moving
     * the cart itself.
     *
     * The renumbering is one transaction and not N saves: a connection lost halfway
     * would otherwise leave the table holding two steps at the same position and a hole
     * where the third was, which is a tunnel the front office then has to repair.
     *
     * @throws CheckoutStepConfigurationException when the move would break the shape of the tunnel
     * @throws PropelException
     */
    public function updatePosition(string $code, int $position): CheckoutStep
    {
        $step = $this->step($code);
        $ordered = $this->orderedSteps();

        $moved = array_values(array_filter(
            $ordered,
            static fn (CheckoutStep $candidate): bool => $candidate->getCode() !== $step->getCode(),
        ));

        $index = max(0, min(\count($moved), $position - 1));
        array_splice($moved, $index, 0, [$step]);

        $this->assertShapeIsSellable($moved);

        $this->inOneTransaction(static function (ConnectionInterface $connection) use ($moved): void {
            foreach ($moved as $rank => $stepToRenumber) {
                $newPosition = $rank + 1;

                if ($stepToRenumber->getPosition() !== $newPosition) {
                    $stepToRenumber->setPosition($newPosition)->save($connection);
                }
            }
        });

        return $step;
    }

    /**
     * Creates the rows missing for the steps the installed code declares, which is what
     * gives a module's step a line in the back office without a migration of its own.
     *
     * It creates and never deletes: a row whose provider is gone holds the order and
     * the wording a merchant wrote, and the module may well be reinstalled.
     *
     * What a provider does not get to decide is that it comes after the money: the
     * position it declares is clamped to the room the tunnel leaves between the cart and
     * the payment, so a module shipping `defaultPosition(): 10` lands before the payment
     * screen instead of behind the confirmation, where nothing would ever show it.
     *
     * @return list<string> the codes it had to create
     *
     * @throws PropelException
     */
    public function synchronize(): array
    {
        $created = [];

        foreach ($this->stepProviders as $provider) {
            $code = $provider->code();

            if (null !== CheckoutStepQuery::create()->findOneByCode($code)) {
                continue;
            }

            (new CheckoutStep())
                ->setCode($code)
                ->setPosition($this->creationPosition($provider))
                ->setActive(1)
                ->setMandatory($provider->isMandatory() ? 1 : 0)
                ->save();

            $created[] = $code;
        }

        sort($created);

        return $created;
    }

    /**
     * Where a step a provider declares lands when its row is created: where it says it
     * belongs, brought back between the cart and the payment when it says otherwise.
     *
     * @throws PropelException
     */
    private function creationPosition(CheckoutStepProviderInterface $provider): int
    {
        $wanted = $provider->defaultPosition();

        $bounds = $this->tunnelShape->creationBounds(
            $provider->code(),
            $this->positionOf(CheckoutStep::CODE_CART),
            $this->positionOf(CheckoutStep::CODE_PAYMENT),
        );

        if (null === $bounds) {
            return $wanted;
        }

        if ($bounds['highest'] < $bounds['lowest']) {
            // Cart and payment stand next to each other: there is no free position
            // between them, so everything from there down moves one step further to
            // open one. Renumbering the whole list instead would silently undo the
            // spacing a merchant left between their own steps.
            $this->pushDownFrom($bounds['lowest']);

            return $bounds['lowest'];
        }

        return max($bounds['lowest'], min($bounds['highest'], $wanted));
    }

    /**
     * @throws PropelException
     */
    private function pushDownFrom(int $position): void
    {
        $this->inOneTransaction(static function (ConnectionInterface $connection) use ($position): void {
            $rows = CheckoutStepQuery::create()
                ->filterByPosition($position, Criteria::GREATER_EQUAL)
                ->orderByPosition(Criteria::DESC)
                ->find($connection);

            foreach ($rows as $row) {
                $row->setPosition($row->getPosition() + 1)->save($connection);
            }
        });
    }

    /**
     * Renumbering is all of it or none of it.
     *
     * A connection lost halfway through would otherwise leave the table holding two
     * steps at the same position and a hole where the third was, which is a tunnel the
     * front office then has to repair on every page it renders.
     *
     * @param callable(ConnectionInterface): void $writes
     *
     * @throws PropelException
     */
    private function inOneTransaction(callable $writes): void
    {
        $connection = Propel::getConnection(CheckoutStepTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $writes($connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }
    }

    /**
     * @throws PropelException
     */
    private function positionOf(string $code): ?int
    {
        return CheckoutStepQuery::create()->findOneByCode($code)?->getPosition();
    }

    /**
     * @param list<CheckoutStep> $ordered
     *
     * @throws CheckoutStepConfigurationException
     */
    private function assertShapeIsSellable(array $ordered): void
    {
        $codes = array_map(static fn (CheckoutStep $step): string => (string) $step->getCode(), $ordered);
        $misplaced = $this->tunnelShape->misplacedCode($codes);

        if (null !== $misplaced) {
            throw new CheckoutStepConfigurationException('The checkout opens on the cart, takes the payment next to last and ends on the confirmation: "%code" cannot be moved there.', parameters: ['%code' => $misplaced]);
        }
    }

    /**
     * @return list<CheckoutStep>
     *
     * @throws PropelException
     */
    private function orderedSteps(): array
    {
        return iterator_to_array(
            CheckoutStepQuery::create()->orderedByTunnel()->find(),
            false,
        );
    }

    /**
     * @throws CheckoutStepConfigurationException when no row names that step
     * @throws PropelException
     */
    private function step(string $code): CheckoutStep
    {
        return CheckoutStepQuery::create()->findOneByCode($code)
            ?? throw new CheckoutStepConfigurationException('There is no checkout step "%code".', parameters: ['%code' => $code]);
    }
}
