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

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

/**
 * Switches the shop between the two order rounding modes.
 *
 * The mode alone is not a safe thing to write. It is shop-wide and it is read
 * when an order is displayed, not when it is placed, so turning it on restates
 * the total of every order already in the database — an invoice the customer has
 * paid, a figure the accounts have booked. What keeps them still is the pivot,
 * `last_sum_of_roundings_order_id`, and nothing in Thelia wrote it: its
 * historical counterpart `last_legacy_rounding_order_id` was written once by the
 * 2.4 upgrade script and never again.
 *
 * So the two writes belong to one command rather than to an operator's hands,
 * and the pivot is written first: interrupted in between, the shop is left with
 * a pivot it does not use yet, which changes nothing.
 */
#[AsCommand(
    name: 'thelia:order:rounding-mode',
    description: 'Show or switch how order line totals are rounded, freezing the orders already placed',
)]
class OrderRoundingModeCommand extends ContainerAwareCommand
{
    private const MODE_NAMES = [
        'sum-of-roundings' => ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS,
        'rounding-of-sums' => ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS,
    ];

    protected function configure(): void
    {
        $this
            ->addArgument(
                'mode',
                InputArgument::OPTIONAL,
                'sum-of-roundings (round the unit price, then multiply) or rounding-of-sums (multiply, then round the line total). Omit to show the current state.',
            )
            ->setHelp(<<<'HELP'
                Shows the rounding mode the shop runs and the pivot that protects
                the orders already placed:

                  <info>php Thelia thelia:order:rounding-mode</info>

                Switches to rounding of sums, which shops selling by weight or by
                volume need — a price per gram is nothing once rounded to the cent.
                The current maximum order id is written to the pivot in the same
                run, so no order already invoiced changes amount:

                  <info>php Thelia thelia:order:rounding-mode rounding-of-sums</info>

                Switching back leaves the pivot in place; it is written again, on
                the orders placed since, the next time the mode is turned on.

                  <info>php Thelia thelia:order:rounding-mode sum-of-roundings</info>
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $requestedMode = $input->getArgument('mode');

        if (null === $requestedMode) {
            $this->showState($style);

            return self::SUCCESS;
        }

        if (!isset(self::MODE_NAMES[$requestedMode])) {
            $style->error(\sprintf('Unknown mode "%s". Expected one of: %s.', $requestedMode, implode(', ', array_keys(self::MODE_NAMES))));

            return self::FAILURE;
        }

        $mode = self::MODE_NAMES[$requestedMode];
        $pivot = (int) (OrderQuery::create()
            ->withColumn('MAX('.OrderTableMap::COL_ID.')', 'max_order_id')
            ->select(['max_order_id'])
            ->findOne() ?? 0);

        $this->showState($style);

        // Running this again on a shop that already switched would move the pivot
        // up to today's last order, and the orders placed since the switch — the
        // ones invoiced under the new rule — would be read back under the old one.
        // That is the very harm the pivot exists to prevent.
        if ($mode === ConfigQuery::getOrderRoundingMode()) {
            $style->comment(\sprintf('The shop already runs %s. Nothing to do.', $requestedMode));

            return self::SUCCESS;
        }

        if (ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode) {
            $style->text([
                \sprintf('Switching to <info>rounding of sums</info> and freezing orders up to id <info>%d</info>.', $pivot),
                'The totals of those orders keep the rule they were invoiced with. Orders placed from now on use the new one.',
            ]);
        } else {
            $style->text('Switching back to <info>sum of roundings</info>, the historical rule. Every order is then read with it.');
        }

        if (!$style->confirm('Apply?', true)) {
            $style->comment('Nothing was written.');

            return self::SUCCESS;
        }

        // The pivot goes first: a run cut short between the two writes leaves a
        // pivot the shop does not use yet, and no amount moves. The other order
        // would leave the new rule applying to the whole order history.
        if (ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $mode) {
            ConfigQuery::write('last_sum_of_roundings_order_id', $pivot, secured: true, hidden: true);
        }

        ConfigQuery::write('order_rounding_mode', $mode);

        // ConfigQuery::write() saves a Config row, and the POST_SAVE listener
        // rebuilds the cached configuration, so the new mode answers the next
        // request without a cache clear.
        $style->success('Done.');
        $this->showState($style);

        return self::SUCCESS;
    }

    private function showState(SymfonyStyle $style): void
    {
        $mode = ConfigQuery::getOrderRoundingMode();
        $modeName = array_search($mode, self::MODE_NAMES, true);
        $pivot = (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0);
        $legacyPivot = (int) ConfigQuery::read('last_legacy_rounding_order_id', 0);

        $style->definitionList(
            ['order_rounding_mode' => \sprintf('%d (%s)', $mode, $modeName)],
            ['last_sum_of_roundings_order_id' => 0 === $pivot ? '0 (no order is frozen)' : (string) $pivot],
            ['last_legacy_rounding_order_id' => 0 === $legacyPivot ? '0 (no pre-2.4 order)' : (string) $legacyPivot],
        );
    }
}
