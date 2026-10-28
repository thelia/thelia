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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Model\AiConnectionQuery;

/**
 * Re-encrypts the stored API keys of the AI connections after the application secret (APP_SECRET) changed.
 *
 * The previous secret never travels as an argument of the command line (it would show in the process list
 * and the shell history): it is read from the environment variable THELIA_AI_PREVIOUS_SECRET, or from the
 * first line of the standard input with --stdin. The keys are rewritten in one transaction: all or none.
 * A key the current secret already opens is left alone, so the command can be run twice.
 */
#[AsCommand(name: 'thelia:ai:rotate-keys', description: 'Re-encrypt the API keys of the AI connections after APP_SECRET changed')]
class AiRotateKeysCommand extends ContainerAwareCommand
{
    public const string PREVIOUS_SECRET_VARIABLE = 'THELIA_AI_PREVIOUS_SECRET';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private readonly string $currentSecret,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Read the previous secret from the first line of the standard input instead of '.self::PREVIOUS_SECRET_VARIABLE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check that every key can be re-encrypted, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $previousSecret = $input->getOption('stdin')
            ? trim((string) fgets($input instanceof StreamableInputInterface && null !== $input->getStream() ? $input->getStream() : \STDIN))
            : trim((string) ($_SERVER[self::PREVIOUS_SECRET_VARIABLE] ?? $_ENV[self::PREVIOUS_SECRET_VARIABLE] ?? getenv(self::PREVIOUS_SECRET_VARIABLE)));

        if ('' === $previousSecret) {
            $io->error(\sprintf('Give the previous secret in the environment variable %s, or on the standard input with --stdin. It is never read from an argument.', self::PREVIOUS_SECRET_VARIABLE));

            return Command::INVALID;
        }

        if (hash_equals($this->currentSecret, $previousSecret)) {
            $io->error('The previous secret is the current one: there is nothing to rotate.');

            return Command::INVALID;
        }

        $current = new AiKeyCipher($this->currentSecret);
        $previous = new AiKeyCipher($previousSecret);

        $rewritten = [];
        $unchanged = [];
        $unreadable = [];

        $connection = Propel::getConnection();
        $connection->beginTransaction();

        try {
            foreach (AiConnectionQuery::create()->filterByApiKeyEncrypted(null, Criteria::ISNOTNULL)->find() as $aiConnection) {
                $blob = (string) $aiConnection->getApiKeyEncrypted();

                if ('' === $blob || null !== $current->decrypt($blob)) {
                    $unchanged[] = $aiConnection->getCode();

                    continue;
                }

                $plain = $previous->decrypt($blob);

                if (null === $plain) {
                    $unreadable[] = $aiConnection->getCode();

                    continue;
                }

                $rewritten[] = $aiConnection->getCode();

                if (!$dryRun) {
                    $aiConnection->setApiKeyEncrypted($current->encrypt($plain))->save($connection);
                }
            }

            if ([] !== $unreadable) {
                $this->rollBack($connection);

                $io->error(\sprintf('Nothing was changed: the previous secret does not open the key of %s.', implode(', ', $unreadable)));

                return Command::FAILURE;
            }

            if ($dryRun) {
                $this->rollBack($connection);
            } else {
                $connection->commit();
            }
        } catch (\Throwable $error) {
            $this->rollBack($connection);

            throw $error;
        }

        $io->success(\sprintf(
            '%s%d key(s) re-encrypted (%s), %d already readable with the current secret.',
            $dryRun ? 'Dry run, nothing written: ' : '',
            \count($rewritten),
            [] === $rewritten ? 'none' : implode(', ', $rewritten),
            \count($unchanged),
        ));

        return Command::SUCCESS;
    }

    private function rollBack(ConnectionInterface $connection): void
    {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}
