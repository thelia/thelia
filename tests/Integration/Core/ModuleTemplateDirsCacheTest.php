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
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\TheliaKernel;
use Thelia\Test\IntegrationTestCase;

/**
 * The template directories of the activated modules are listed once and read
 * from a cache file on every request afterwards, so the list outlives what it
 * describes. A parser handed a directory that is no longer there refuses to
 * load anything at all, and the back office answers 500 with a message naming
 * a path rather than the stale list.
 */
final class ModuleTemplateDirsCacheTest extends IntegrationTestCase
{
    private string $cacheFile;
    private ?string $savedList = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheFile = static::$kernel->getCacheDir().\DIRECTORY_SEPARATOR.'module_template_dirs.php';
        $this->savedList = is_file($this->cacheFile) ? (string) file_get_contents($this->cacheFile) : null;
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        null === $this->savedList
            ? $filesystem->remove($this->cacheFile)
            : $filesystem->dumpFile($this->cacheFile, $this->savedList);

        parent::tearDown();
    }

    #[Test]
    public function aDirectoryThatIsGoneIsLeftOutOfTheList(): void
    {
        $onDisk = [TemplateDefinition::BACK_OFFICE, 'default-twig', THELIA_ROOT.'templates', 'Probe'];
        $gone = [TemplateDefinition::BACK_OFFICE, 'default-twig', THELIA_ROOT.'templates/gone-with-an-upgrade', 'Probe'];

        $this->writeList([$onDisk, $gone]);

        self::assertSame([$onDisk], $this->readList());
    }

    #[Test]
    public function aListThatIsStillTrueIsReadAsItIs(): void
    {
        $list = [[TemplateDefinition::FRONT_OFFICE, 'flexy', THELIA_ROOT.'templates', 'Probe']];

        $this->writeList($list);

        self::assertSame($list, $this->readList());
    }

    /**
     * @param list<array{int, string, string, string}> $templateDirs
     */
    private function writeList(array $templateDirs): void
    {
        (new Filesystem())->dumpFile(
            $this->cacheFile,
            '<?php return '.var_export($templateDirs, true).';',
        );
    }

    /**
     * @return list<array{int, string, string, string}>
     */
    private function readList(): array
    {
        return (new \ReflectionMethod(TheliaKernel::class, 'getModuleTemplateDirs'))
            ->invoke(static::$kernel);
    }
}
