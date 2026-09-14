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

use Propel\Runtime\Exception\PropelException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutStepView;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Service\Step\CheckoutStepProviderInterface;
use Thelia\Model\Cart;
use Thelia\Model\CheckoutStep;
use Thelia\Model\CheckoutStepQuery;

/**
 * The steps a given cart walks through, and the first one it still has something to do
 * at.
 *
 * This is the one place that answers "may this buyer be shown that screen": the theme,
 * the next-button guard and the back office all read the same list, so a step turned
 * off disappears everywhere at once instead of in whichever template remembered to ask.
 *
 * The cart is always a parameter and never read from the session: the same answers have
 * to be reachable from a command line and from the API, where there is no session to
 * read one from.
 *
 * What it never does is refuse to answer. A `checkout_step` table that lost the cart,
 * the payment or the confirmation step, or that holds them in an order nothing can be
 * sold in — a half-applied migration, a module that wrote straight into it — would
 * leave a shop with a tunnel it cannot sell through, so a configuration CheckoutTunnelShape
 * rejects is logged and the list the code declares is used instead. It is the same
 * object the back office refuses a move with, so what is refused at the writing side is
 * exactly what is repaired at the reading side.
 *
 * Memoized per cart and per state of that cart for the request: the delivery check asks
 * the shipping modules what they would quote, which is not something to run twice for
 * one page, and the key carries the cart's timestamp and the choices made on it, so a
 * cart that changed is answered about as it is now rather than as it was when the first
 * question was asked — freshness used to be a convention every caller had to remember.
 * The cache is dropped altogether whenever a step is reordered, toggled or created, and
 * on kernel.reset, which is what keeps a runtime serving several requests from one
 * process — FrankenPHP, RoadRunner — from answering the second buyer with the first
 * one's tunnel.
 */
final class CheckoutProgressionService implements EventSubscriberInterface, ResetInterface
{
    /** @var array<string, list<CheckoutStepView>> */
    private array $activeStepsCache = [];

    /** @var array<string, bool> */
    private array $checkCache = [];

    /** @var array<string, CheckoutStepProviderInterface>|null */
    private ?array $providersByCode = null;

    /**
     * @param iterable<CheckoutStepProviderInterface> $stepProviders
     */
    public function __construct(
        #[AutowireIterator('thelia.checkout.step_provider')]
        private readonly iterable $stepProviders,
        private readonly CheckoutTunnelShape $tunnelShape,
        private readonly CheckoutStepTitleResolver $titleResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * The steps this cart is shown, in the order the merchant put them in.
     *
     * A step the cart has nothing to do at — the delivery of a cart with nothing to
     * ship — is left out: it would otherwise be an empty screen the buyer has to click
     * through.
     *
     * @param string|null $locale the language to read the wordings in; the shop default
     *                            when null, so that a caller with no request behind it
     *                            still gets titles
     *
     * @return list<CheckoutStepView>
     *
     * @throws PropelException
     */
    public function activeSteps(Cart $cart, ?string $locale = null): array
    {
        $key = $this->cartKey($cart).'|'.($locale ?? '');

        return $this->activeStepsCache[$key] ??= $this->buildActiveSteps($cart, $locale);
    }

    /**
     * The first step this cart has not satisfied yet, or null when it is ready to be
     * ordered. This is what a checkout redirects a buyer to when they land further down
     * the tunnel than they have got to.
     *
     * @throws PropelException
     */
    public function firstIncompleteStep(Cart $cart): ?CheckoutStepView
    {
        foreach ($this->activeSteps($cart) as $step) {
            if (!$this->passes($cart, $step->code)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Whether this cart may be shown that step: every step before it is settled.
     *
     * A code the cart's tunnel does not hold — an unknown step, or one left out for
     * this cart — is not reachable: there is no such screen to show.
     *
     * @throws PropelException
     */
    public function isReachable(Cart $cart, string $stepCode): bool
    {
        foreach ($this->activeSteps($cart) as $step) {
            if ($step->code === $stepCode) {
                return true;
            }

            if (!$this->passes($cart, $step->code)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Drops everything memoized, so the next question is answered against the table and
     * the carts as they are now.
     */
    public function forget(): void
    {
        $this->activeStepsCache = [];
        $this->checkCache = [];
        $this->providersByCode = null;
    }

    /**
     * The kernel dropping everything a service remembered between two requests is the
     * same question as forget(), asked by the runtime instead of by the back office.
     */
    public function reset(): void
    {
        $this->forget();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CHECKOUT_STEP_TOGGLE_ACTIVE => ['forget', 0],
            TheliaEvents::CHECKOUT_STEP_UPDATE_POSITION => ['forget', 0],
            TheliaEvents::CHECKOUT_STEP_SYNCHRONIZE => ['forget', 0],
        ];
    }

    /**
     * @return list<CheckoutStepView>
     *
     * @throws PropelException
     */
    private function buildActiveSteps(Cart $cart, ?string $locale): array
    {
        $providers = $this->providersByCode();
        $rows = $this->rowsByCode();
        $views = [];

        foreach ($this->orderedCodes($providers, $rows) as $code) {
            $provider = $providers[$code];

            if ($provider->isSkippedFor($cart)) {
                continue;
            }

            $row = $rows[$code] ?? null;

            // The rank the step is served at, not the number written in the table: a
            // step the shop has not synchronised yet is ordered by a default position
            // that says where it belongs and nothing about how far along the tunnel it
            // stands, and a skipped step leaves a hole in the stored numbering. A theme
            // that numbers or sorts on this has to read the tunnel it is being shown.
            $views[] = new CheckoutStepView(
                code: $code,
                title: $this->title($code, $row, $locale),
                position: \count($views) + 1,
                mandatory: $row?->isMandatory() ?? $provider->isMandatory(),
                componentName: $provider->componentName(),
            );
        }

        return $views;
    }

    /**
     * The codes of the tunnel, ordered, whatever state the table is in.
     *
     * @param array<string, CheckoutStepProviderInterface> $providers
     * @param array<string, CheckoutStep>                  $rows
     *
     * @return list<string>
     */
    private function orderedCodes(array $providers, array $rows): array
    {
        $positions = [];
        $unsynced = [];

        foreach ($rows as $code => $row) {
            if (!$row->isActive()) {
                continue;
            }

            if (!isset($providers[$code])) {
                // Nothing installed declares this step: there is no screen to render and
                // no check to run, so showing it would be a dead end in the tunnel.
                $this->logger->warning(\sprintf('Checkout step "%s" is configured but no step provider declares it: it is left out of the checkout.', $code));

                continue;
            }

            $positions[$code] = $row->getPosition();
        }

        foreach ($providers as $code => $provider) {
            if (isset($rows[$code])) {
                continue;
            }

            // A module that declares a step the shop has not synchronised yet still
            // gets its screen. A required step stands where the code says; any other
            // is placed where the synchronisation would create its row, between the
            // cart and the payment, so that a `defaultPosition(): 10` never lands
            // behind the confirmation, where nothing would ever show it.
            if (\in_array($code, CheckoutStep::REQUIRED_CODES, true)) {
                $positions[$code] = $provider->defaultPosition();

                continue;
            }

            $unsynced[$code] = $provider->defaultPosition();
        }

        $codes = $this->withUnsyncedSteps($this->sortByPositionThenCode($positions), $positions, $unsynced);
        $unsellable = $this->unsellableReason($codes);

        if (null !== $unsellable) {
            $this->logger->warning(\sprintf('The checkout step configuration is not one a shop can sell through: %s. The steps declared by the code are used instead.', $unsellable));

            $codes = $this->declaredCodes($providers);
        }

        return $codes;
    }

    /**
     * The tunnel the code alone declares: the required steps at their default position,
     * every other step brought back between the cart and the payment.
     *
     * @param array<string, CheckoutStepProviderInterface> $providers
     *
     * @return list<string>
     */
    private function declaredCodes(array $providers): array
    {
        $positions = [];
        $others = [];

        foreach ($providers as $code => $provider) {
            if (\in_array($code, CheckoutStep::REQUIRED_CODES, true)) {
                $positions[$code] = $provider->defaultPosition();

                continue;
            }

            $others[$code] = $provider->defaultPosition();
        }

        return $this->withUnsyncedSteps($this->sortByPositionThenCode($positions), $positions, $others);
    }

    /**
     * Slots the steps no row places into an ordered tunnel, each where it says it
     * belongs when the tunnel has room there, and right before the payment otherwise —
     * the same clamp CheckoutStepConfigurationService applies when it creates the row.
     *
     * @param list<string>       $codes     the ordered tunnel so far
     * @param array<string, int> $positions the position of each code of $codes
     * @param array<string, int> $wanted    the default position of each step to slot in
     *
     * @return list<string>
     */
    private function withUnsyncedSteps(array $codes, array $positions, array $wanted): array
    {
        foreach ($this->sortByPositionThenCode($wanted) as $code) {
            $position = $wanted[$code];
            $bounds = $this->tunnelShape->creationBounds(
                $code,
                $positions[CheckoutStep::CODE_CART] ?? null,
                $positions[CheckoutStep::CODE_PAYMENT] ?? null,
            );

            if (null !== $bounds) {
                $position = $bounds['highest'] < $bounds['lowest']
                    ? $bounds['lowest']
                    : max($bounds['lowest'], min($bounds['highest'], $position));
            }

            // Sharing a position with the payment, when the tunnel leaves no room ahead
            // of it, means standing before it and never behind: the money is taken next
            // to last, whatever the code of the step that shares its position.
            $slot = [$position, 0, $code];
            $index = \count($codes);

            foreach ($codes as $rank => $existing) {
                $existingSlot = [$positions[$existing], \in_array($existing, CheckoutStep::REQUIRED_CODES, true) ? 1 : 0, $existing];

                if ($existingSlot > $slot) {
                    $index = $rank;

                    break;
                }
            }

            array_splice($codes, $index, 0, [$code]);
            $positions[$code] = $position;
        }

        return $codes;
    }

    /**
     * Why this order cannot be sold through, in the words the merchant needs to find
     * the row to fix — or null when it can.
     *
     * A missing step and a misplaced one are told apart on purpose: a payment step that
     * was turned off and a payment step that a hand-written position pushed ahead of the
     * cart are two different rows to go and look at.
     *
     * @param list<string> $codes
     */
    private function unsellableReason(array $codes): ?string
    {
        if ([] === $codes) {
            return 'it holds no step at all';
        }

        $missing = $this->tunnelShape->missingCode($codes);

        if (null !== $missing) {
            return \sprintf('the step "%s", which a checkout cannot do without, is missing', $missing);
        }

        $misplaced = $this->tunnelShape->misplacedCode($codes);

        if (null !== $misplaced) {
            return \sprintf('the checkout opens on the cart, takes the money next to last and ends on the confirmation, and the step "%s" stands elsewhere (%s)', $misplaced, implode(', ', $codes));
        }

        return null;
    }

    /**
     * @param array<string, int> $positions
     *
     * @return list<string>
     */
    private function sortByPositionThenCode(array $positions): array
    {
        $codes = array_keys($positions);

        // Two steps sharing a position is not an error the buyer should see as a tunnel
        // whose order changes between two page loads, so the code settles the tie.
        usort($codes, static fn (string $left, string $right): int => [$positions[$left], $left] <=> [$positions[$right], $right]);

        return $codes;
    }

    /**
     * A step no row names yet — a module just installed, a table left behind by a
     * deployment — is named by its code, which is the only wording anything has.
     *
     * @throws PropelException
     */
    private function title(string $code, ?CheckoutStep $row, ?string $locale): string
    {
        return null === $row ? $code : $this->titleResolver->titleOf($row, $locale);
    }

    /**
     * @throws PropelException
     */
    private function passes(Cart $cart, string $code): bool
    {
        $key = $this->cartKey($cart).'|'.$code;

        if (isset($this->checkCache[$key])) {
            return $this->checkCache[$key];
        }

        $provider = $this->providersByCode()[$code] ?? null;

        if (null === $provider) {
            return $this->checkCache[$key] = true;
        }

        try {
            $provider->check($cart);
            $passes = true;
        } catch (CheckoutException) {
            $passes = false;
        }

        return $this->checkCache[$key] = $passes;
    }

    /**
     * @return array<string, CheckoutStepProviderInterface>
     */
    private function providersByCode(): array
    {
        if (null !== $this->providersByCode) {
            return $this->providersByCode;
        }

        $providers = [];

        foreach ($this->stepProviders as $provider) {
            $providers[$provider->code()] = $provider;
        }

        return $this->providersByCode = $providers;
    }

    /**
     * The merchant's rows, or none at all when the table is not there yet.
     *
     * Every page of the tunnel reads this, so a deployment that ships the code before
     * the SQL would turn the whole checkout into a 500 rather than into a shop running
     * on its defaults for a minute. It is the same answer the service already gives to a
     * table holding a configuration nobody can sell through: log what is wrong and fall
     * back on the steps the code declares.
     *
     * @return array<string, CheckoutStep>
     *
     * @throws PropelException
     */
    private function rowsByCode(): array
    {
        try {
            $found = CheckoutStepQuery::create()->orderedByTunnel()->find();
        } catch (\Throwable $throwable) {
            if (!$this->isMissingTable($throwable)) {
                throw $throwable;
            }

            $this->logger->warning('The `checkout_step` table does not exist: the checkout runs on the steps the code declares. Apply the pending database updates.', ['exception' => $throwable]);

            return [];
        }

        $rows = [];

        foreach ($found as $row) {
            $rows[(string) $row->getCode()] = $row;
        }

        return $rows;
    }

    /**
     * Whether the database answered "there is no such table" rather than anything else.
     *
     * Told apart by the SQLSTATE the driver raised and not by the message, which is the
     * server's to word and to translate. Everything else — a connection refused, a
     * column missing after a half-applied migration — is a real incident and is left to
     * blow up.
     */
    private function isMissingTable(\Throwable $throwable): bool
    {
        for ($cause = $throwable; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof \PDOException && '42S02' === (string) $cause->getCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the memo is kept under: which cart, and which state of it.
     *
     * The state is the cart's `updated_at` and the four choices the steps are asked
     * about, so a cart that was changed answers with a new key instead of with the
     * tunnel of a cart that no longer exists — the callers no longer have to remember to
     * call forget(), which they did not always do. The columns are read on purpose and
     * not only the timestamp: `updated_at` is a DATETIME, so it tells one second from the
     * next, and a buyer who picks a carrier settles the delivery step well within one.
     *
     * forget() stays, for what this cannot see: a row of `checkout_step` that moved, a
     * consent that was turned on, an item added to the cart by something that did not
     * touch the cart row.
     *
     * A cart with no id yet — one being built by a command, or by a test — is still
     * worth memoizing, and its identity is the object itself.
     */
    private function cartKey(Cart $cart): string
    {
        $stateVersion = $cart->getUpdatedAt('U.u');

        return implode('|', [
            $cart->getId() ?? 'unsaved-'.spl_object_id($cart),
            \is_string($stateVersion) ? $stateVersion : 'never-saved',
            (string) $cart->getAddressDeliveryId(),
            (string) $cart->getAddressInvoiceId(),
            (string) $cart->getDeliveryModuleId(),
            (string) $cart->getPaymentModuleId(),
        ]);
    }
}
