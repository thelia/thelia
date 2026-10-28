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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Domain\Ai\Service\AiUsageRegister;
use Thelia\Domain\Ai\Service\AiUsageRegisterExport;

/**
 * The register of the AI usages of the shop: module, purpose, audience, range, transparency duty, notice and mark
 * on or off, state of compliance with the AI Act, jurisdiction of the connection. The merchant keeps the export
 * as the record of the AI he runs; the JSON is the one the admin API serves.
 */
#[AsCommand(name: 'thelia:ai:usages', description: 'List the AI usages of the shop with their AI Act compliance status (table, json or csv)')]
class AiUsagesCommand extends ContainerAwareCommand
{
    public const array FORMATS = ['table', 'json', 'csv'];

    public function __construct(private readonly AiUsageRegister $register, private readonly AiUsageRegisterExport $export = new AiUsageRegisterExport())
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table, json or csv', 'table')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language of the compliance messages (default: the default language of the shop)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = (string) $input->getOption('format');

        if (!\in_array($format, self::FORMATS, true)) {
            $io->error(\sprintf('Unknown format "%s": use %s.', $format, implode(', ', self::FORMATS)));

            return Command::INVALID;
        }

        $locale = $input->getOption('locale');
        $rows = $this->register->rows(\is_string($locale) && '' !== $locale ? $locale : null);

        match ($format) {
            'json' => $output->writeln($this->export->json($rows)),
            'csv' => $output->write($this->export->csv($rows)),
            default => $this->table($io, $rows),
        };

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function table(SymfonyStyle $io, array $rows): void
    {
        $io->table(AiUsageRegisterExport::COLUMNS, array_map($this->export->cells(...), $rows));
    }
}
