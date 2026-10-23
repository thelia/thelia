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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

/**
 * A competing request in a PHP process of its own, for the sequences a second
 * connection of the test process cannot play: a request that commits, or asks
 * for one more lock, while the request under test is really waiting on it.
 *
 * Nothing is left to timing. The process runs its first steps and says
 * "ready"; it then watches the database session of the request under test and
 * runs its last steps only once it has seen that session stuck for a while on
 * one statement that takes a lock - a `FOR UPDATE`, an update, or an insert
 * checking its foreign keys - which only a lock wait does. Having never seen it,
 * it rolls back and says so, and the test fails as having proved nothing.
 *
 * A step is one of:
 *  - ['lock', 'order'|'order_product', id]: `SELECT ... FOR UPDATE` on the row;
 *  - ['update', 'order_return_line', id, [column => value, ...]]: updates the
 *    row, which holds its lock until the end of the transaction;
 *  - ['return', order id, customer id, status id, include postage, [[order product id, quantity], ...]]:
 *    writes a return the way a request that passed the checks does;
 *  - ['ballast', rows]: writes that many throwaway rows, which makes this
 *    transaction the heavier one, the one the database keeps when it breaks a
 *    deadlock - the request under test is then the one it gives up on.
 *
 * Requires SecondConnectionTrait, for the configuration of the test database.
 */
trait CompetingRequestTrait
{
    /** How long either side waits for the other before giving up. */
    private const int COMPETING_PATIENCE_SECONDS = 10;

    /** @var resource|null */
    private $competingProcess;

    /** @var array<int, resource> */
    private array $competingPipes = [];

    /**
     * @param list<list<mixed>> $before      steps run before saying ready
     * @param list<list<mixed>> $onceWaiting steps run once the request under test is seen waiting
     * @param bool              $commit      whether the process commits at the end, or rolls back
     */
    private function startCompetingRequest(int $waitingThreadId, array $before, array $onceWaiting = [], bool $commit = true): void
    {
        $script = <<<'PHP'
            <?php
            $c = json_decode((string) getenv('COMPETING_REQUEST'), true, flags: JSON_THROW_ON_ERROR);
            $pdo = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = '.(int) $c['patience']);
            $run = static function (array $steps) use ($pdo): void {
                foreach ($steps as $step) {
                    switch ($step[0]) {
                        case 'lock':
                            $statement = $pdo->prepare(sprintf('SELECT `id` FROM `%s` WHERE `id` = ? FOR UPDATE', $step[1]));
                            $statement->execute([$step[2]]);
                            $statement->closeCursor();
                            break;
                        case 'update':
                            $columns = array_keys($step[3]);
                            $pdo->prepare(sprintf('UPDATE `%s` SET %s WHERE `id` = ?', $step[1], implode(', ', array_map(static fn (string $column): string => '`'.$column.'` = ?', $columns))))
                                ->execute([...array_values($step[3]), $step[2]]);
                            break;
                        case 'return':
                            [, $orderId, $customerId, $statusId, $includePostage, $lines] = $step;
                            $pdo->prepare('INSERT INTO `order_return` (`order_id`, `customer_id`, `status_id`, `include_postage`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, NOW(), NOW())')
                                ->execute([$orderId, $customerId, $statusId, $includePostage ? 1 : 0]);
                            $returnId = (int) $pdo->lastInsertId();
                            foreach ($lines as [$orderProductId, $quantity]) {
                                $pdo->prepare('INSERT INTO `order_return_line` (`order_return_id`, `order_product_id`, `quantity`, `created_at`, `updated_at`) VALUES (?, ?, ?, NOW(), NOW())')
                                    ->execute([$returnId, $orderProductId, $quantity]);
                            }
                            break;
                        case 'ballast':
                            $insert = $pdo->prepare('INSERT INTO `config` (`name`, `value`, `created_at`, `updated_at`) VALUES (?, ?, NOW(), NOW())');
                            for ($row = 0; $row < $step[1]; ++$row) {
                                $insert->execute([uniqid('competing-request-ballast-', true), '1']);
                            }
                            break;
                        default:
                            throw new LogicException('Unknown step '.$step[0]);
                    }
                }
            };

            try {
                $pdo->beginTransaction();
                $run($c['before']);
                fwrite(STDOUT, "ready\n");
                fflush(STDOUT);

                // A session of the same account is visible without the PROCESS
                // privilege. One statement seen running over several polls is a
                // statement waiting on a lock: nothing else the request under
                // test sends takes that long.
                $running = $pdo->prepare("SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ? AND COMMAND IN ('Query', 'Execute') AND (INFO LIKE '%FOR UPDATE%' OR INFO LIKE 'INSERT INTO%' OR INFO LIKE 'UPDATE %')");
                $deadline = microtime(true) + $c['patience'];
                $seenFor = 0;
                $seen = null;
                while ($seenFor < 10 && microtime(true) < $deadline) {
                    $running->execute([$c['waiting_thread_id']]);
                    $info = $running->fetchColumn();
                    $running->closeCursor();
                    $seenFor = (false !== $info && null !== $info && $info === $seen) ? $seenFor + 1 : 0;
                    $seen = false === $info ? null : $info;
                    usleep(15000);
                }

                if ($seenFor < 10) {
                    $pdo->rollBack();
                    fwrite(STDOUT, "never saw the request under test wait\n");
                    exit(3);
                }

                fwrite(STDOUT, 'saw the request under test wait on: '.preg_replace('/\s+/', ' ', (string) $seen)."\n");
                $run($c['once_waiting']);

                if ($c['commit']) {
                    $pdo->commit();
                    fwrite(STDOUT, "committed\n");
                } else {
                    $pdo->rollBack();
                    fwrite(STDOUT, "rolled back\n");
                }
            } catch (PDOException $exception) {
                fwrite(STDOUT, 'failed: '.($exception->errorInfo[1] ?? '?').' '.$exception->getMessage()."\n");
                exit(4);
            }
            PHP;

        $database = $this->secondConnectionConfiguration();

        $configuration = json_encode([
            'dsn' => $database['dsn'],
            'user' => $database['user'] ?? null,
            'password' => $database['password'] ?? null,
            'patience' => self::COMPETING_PATIENCE_SECONDS,
            'waiting_thread_id' => $waitingThreadId,
            'before' => $before,
            'once_waiting' => $onceWaiting,
            'commit' => $commit,
        ], \JSON_THROW_ON_ERROR);

        // The full parent environment: a runner whose php.ini comes from
        // PHP_INI_SCAN_DIR or PHPRC would otherwise hand the child a PHP with
        // no pdo_mysql.
        $process = proc_open(
            [\PHP_BINARY],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), 'COMPETING_REQUEST' => $configuration],
        );
        self::assertIsResource($process, 'The competing process could not be started.');

        $this->competingProcess = $process;
        $this->competingPipes = $pipes;

        fwrite($pipes[0], $script);
        fclose($pipes[0]);

        $read = [$pipes[1]];
        $none = [];
        $ready = stream_select($read, $none, $none, self::COMPETING_PATIENCE_SECONDS) > 0 ? fgets($pipes[1]) : false;

        if ("ready\n" !== $ready) {
            self::fail('The competing process did not run its first steps: '.$ready.stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
        }
    }

    /**
     * Waits for the competing process to end and checks it ran every step
     * after seeing the request under test wait: otherwise the request under
     * test never met it, and the test proved nothing.
     *
     * @return string what the process said
     */
    private function finishCompetingRequest(): string
    {
        $output = (string) stream_get_contents($this->competingPipes[1]);
        $errors = (string) stream_get_contents($this->competingPipes[2]);
        $exitCode = $this->stopCompetingRequest();

        self::assertStringContainsString('saw the request under test wait', $output, 'The request under test never waited on the competing one, so the test proved nothing: '.$output.$errors);
        self::assertSame(0, $exitCode, 'The competing request failed: '.$output.$errors);

        return $output;
    }

    private function stopCompetingRequest(): ?int
    {
        if (null === $this->competingProcess) {
            return null;
        }

        foreach ($this->competingPipes as $index => $pipe) {
            if (0 !== $index && \is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($this->competingProcess);
        $this->competingProcess = null;
        $this->competingPipes = [];

        return $exitCode;
    }
}
