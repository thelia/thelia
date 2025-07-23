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

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/core',
        __DIR__ . '/src',
        __DIR__ . '/setup',
        __DIR__ . '/public/install',
        __DIR__ . '/tests',
    ])
    ->exclude([
        'var',
        'vendor',
        'node_modules',
        'cache',
        'log',
    ])
    ->notPath([
        'core/lib/Thelia/Model/Base/*',
        'core/lib/Thelia/Model/Map/*',
        'core/lib/Thelia/Model/om/*',
        'local/modules/*/Model/Base/*',
        'local/modules/*/Model/Map/*',
        'local/modules/*/Model/om/*',
    ])
    ->name('*.php')
    ->notName('*.tpl')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

(new Symfony\Component\Filesystem\Filesystem())->mkdir(__DIR__ . '/var/cache-ci');

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/var/cache-ci/.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'yoda_style' => false,
        'declare_strict_types' => true,
        'header_comment' => [
            'header' => implode("\n", [
                'This file is part of the Thelia package.',
                'http://www.thelia.net',
                '',
                '(c) OpenStudio <info@thelia.net>',
                '',
                'For the full copyright and license information, please view the LICENSE',
                'file that was distributed with this source code.',
            ]),
        ],
    ])
    ->setFinder($finder);
