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

namespace Thelia\Tests\Integration\Core;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * A shop with no configuration in its database is not installed, and the
 * kernel has to say so before it builds a container: the container describes
 * services built on Propel models that a shop which has never been installed
 * has not generated, so building it first turns a plain "not installed" into
 * a fatal error naming an unrelated class.
 *
 * The kernel is booted in a subprocess: Propel keeps its configuration in
 * process-wide state, so a kernel pointed at another database has to be a
 * process of its own.
 */
final class TheliaKernelNotInstalledTest extends TestCase
{
    private const REFUSED = 'REFUSED: Thelia is not installed';

    /**
     * A database every MySQL and MariaDB server holds, that every account can
     * read, and that holds no shop: it stands for a database that answers but
     * has never been installed into, without the test needing the right to
     * create one.
     */
    private const DATABASE_WITHOUT_A_SHOP = 'information_schema';

    private string $environment;
    private string $script;

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->environment = 'notinstalled'.$suffix;
        $this->script = sys_get_temp_dir().'/thelia-not-installed-'.$suffix.'.php';

        (new Filesystem())->dumpFile($this->script, $this->bootScript());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([
            $this->script,
            THELIA_ROOT.'var/cache/'.$this->environment,
            THELIA_ROOT.'var/propel/'.$this->environment,
        ]);
    }

    #[Test]
    public function aDatabaseThatDoesNotExistIsNotInstalled(): void
    {
        $output = $this->bootKernelAgainst('thelia_no_such_database_'.bin2hex(random_bytes(4)));

        self::assertStringContainsString(self::REFUSED, $output);
    }

    #[Test]
    public function aDatabaseWithNoConfigurationIsNotInstalled(): void
    {
        $output = $this->bootKernelAgainst(self::DATABASE_WITHOUT_A_SHOP);

        self::assertStringContainsString(self::REFUSED, $output);
    }

    #[Test]
    public function noContainerIsBuiltForAShopThatIsNotInstalled(): void
    {
        $this->bootKernelAgainst(self::DATABASE_WITHOUT_A_SHOP);

        $cacheDirectory = THELIA_ROOT.'var/cache/'.$this->environment;

        self::assertSame(
            [],
            glob($cacheDirectory.'/*Container*') ?: [],
            'The container must not be built before the shop is known to be installed.',
        );
    }

    private function bootKernelAgainst(string $databaseName): string
    {
        $process = new Process(
            [\PHP_BINARY, $this->script],
            env: ['THELIA_PROBE_DATABASE_NAME' => $databaseName],
        );
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }

    /**
     * Boots the project kernel against the database named in the environment
     * and reports what it did, so the assertions read one line of output
     * instead of the state of a process that is already gone.
     */
    private function bootScript(): string
    {
        $script = <<<'BOOT'
            <?php

            require '%1$svendor/autoload.php';

            (new Symfony\Component\Dotenv\Dotenv())->bootEnv('%1$s.env');

            $database = getenv('THELIA_PROBE_DATABASE_NAME');
            $_SERVER['DATABASE_NAME'] = $_ENV['DATABASE_NAME'] = $database;
            putenv('DATABASE_NAME='.$database);

            try {
                (new App\Kernel('%2$s', false))->handle(
                    Symfony\Component\HttpFoundation\Request::create('/'),
                );

                echo "BOOTED\n";
            } catch (RuntimeException $exception) {
                echo 'REFUSED: '.$exception->getMessage()."\n";
            } catch (Throwable $exception) {
                echo 'FAILED: '.$exception::class.': '.$exception->getMessage()."\n";
            }
            BOOT;

        return \sprintf($script, THELIA_ROOT, $this->environment);
    }
}
