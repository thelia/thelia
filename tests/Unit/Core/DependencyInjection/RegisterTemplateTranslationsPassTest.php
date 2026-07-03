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

namespace Thelia\Tests\Unit\Core\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\Compiler\RegisterTemplateTranslationsPass;

final class RegisterTemplateTranslationsPassTest extends TestCase
{
    public function testToleratesMissingTemplateDirectories(): void
    {
        $projectDir = sys_get_temp_dir().'/thelia-translations-pass-'.uniqid('', true);
        mkdir($projectDir.'/templates', 0o777, true);

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $projectDir);
            $container->setDefinition('translator.default', new Definition(\stdClass::class));

            (new RegisterTemplateTranslationsPass())->process($container);

            self::assertSame([], $container->getDefinition('translator.default')->getMethodCalls());
        } finally {
            rmdir($projectDir.'/templates');
            rmdir($projectDir);
        }
    }

    public function testRegistersCatalogsFromExistingTemplateDirectories(): void
    {
        $projectDir = sys_get_temp_dir().'/thelia-translations-pass-'.uniqid('', true);
        $translationsDir = $projectDir.'/templates/email/default/translations';
        mkdir($translationsDir, 0o777, true);
        file_put_contents($translationsDir.'/email.fr_FR.php', "<?php return [];\n");

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $projectDir);
            $container->setDefinition('translator.default', new Definition(\stdClass::class));

            (new RegisterTemplateTranslationsPass())->process($container);

            $calls = $container->getDefinition('translator.default')->getMethodCalls();
            self::assertCount(1, $calls);
            self::assertSame('addResource', $calls[0][0]);
            self::assertSame(['php', $translationsDir.'/email.fr_FR.php', 'fr_FR', 'email'], $calls[0][1]);
        } finally {
            unlink($translationsDir.'/email.fr_FR.php');
            rmdir($translationsDir);
            rmdir($projectDir.'/templates/email/default');
            rmdir($projectDir.'/templates/email');
            rmdir($projectDir.'/templates');
            rmdir($projectDir);
        }
    }
}
