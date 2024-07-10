<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        '.docker',
        'config',
        'local/modules/OpenApi',
        'local/modules/SmartyRedirection',
        'local/modules/ChoiceFilter',
        'local/modules/StoreSeo',
        'local/modules/BetterSeo',
        'local/modules/ShortCode',
        'local/modules/ShortCodeMeta',
        'local/modules/ForcePhone',
        'local/modules/HookTest',
        'local/modules/CustomDelivery',
        'local/modules/Tinymce/Resources',
        'local/modules/TheliaLibrary',
        'local/modules/TheliaBlocks',
        'local/modules/ProductLoopAttributeFilter',
    ])
;

(new Filesystem())->mkdir(__DIR__.'/var/cache-ci');

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/var/cache-ci/.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP71Migration' => true,
        '@PHP71Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'yoda_style' => false,
        'single_line_throw' => false,
        'declare_strict_types' => false,
        'phpdoc_order' => true,
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
    ->setFinder($finder)
    ;
