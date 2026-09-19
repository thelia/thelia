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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Model\CatalogPriceRuleQuery;

/**
 * The catch-up of the catalog price rules: what the events did not replay inline.
 *
 * Run from the system scheduler alongside sale:check-activation. By default it
 * finishes the rules left dirty by a change too large for the request that made
 * it, and purges the stored segments already over. It can also rebuild
 * everything, one rule, or one product.
 */
#[AsCommand(
    name: 'catalog-price-rule:recompute',
    description: 'Recompute the stored prices of the catalog price rules: the dirty rules by default, everything with --full.',
)]
class CatalogPriceRuleRecomputeCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly RuleRepricer $repricer,
        private readonly PublicPriceSegmentWriter $writer,
        private readonly ResourceCache $resourceCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', null, InputOption::VALUE_NONE, 'Rebuild the scope and the stored prices of every rule')
            ->addOption('rule', null, InputOption::VALUE_REQUIRED, 'Rebuild one rule, by id')
            ->addOption('product', null, InputOption::VALUE_REQUIRED, 'Re-evaluate one product against every rule, by id')
            ->addOption('purge-only', null, InputOption::VALUE_NONE, 'Only drop the stored segments already over');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $purged = $this->writer->purgeExpired();
            $output->writeln(\sprintf('<info>%d expired price segment(s) purged</info>', $purged));

            if ($input->getOption('purge-only')) {
                return 0;
            }

            if (null !== $ruleId = $input->getOption('rule')) {
                $rule = CatalogPriceRuleQuery::create()->findPk((int) $ruleId);

                if (null === $rule) {
                    $output->writeln(\sprintf('<error>No catalog price rule with id %d</error>', (int) $ruleId));

                    return 1;
                }

                $this->repricer->repriceRule($rule);
                $output->writeln(\sprintf('<info>Rule %d recomputed</info>', $rule->getId()));
            } elseif (null !== $productId = $input->getOption('product')) {
                $this->repricer->afterProductChanged((int) $productId);
                $output->writeln(\sprintf('<info>Product %d re-evaluated</info>', (int) $productId));
            } elseif ($input->getOption('full')) {
                $count = 0;

                foreach (CatalogPriceRuleQuery::create()->find() as $rule) {
                    $this->repricer->repriceRule($rule);
                    ++$count;
                }

                $written = $this->writer->recomputeAll();
                $output->writeln(\sprintf('<info>%d rule(s) rematerialized, %d price segment(s) written</info>', $count, $written));
            } else {
                $count = $this->repricer->repriceDirtyRules();
                $output->writeln(\sprintf('<info>%d dirty rule(s) recomputed</info>', $count));
            }

            // The shared data access cache may hold prices this run just changed.
            $this->resourceCache->clear();
        } catch (\Exception $exception) {
            $output->writeln(\sprintf('<error>Error : %s</error>', $exception->getMessage()));

            return 1;
        }

        return 0;
    }
}
