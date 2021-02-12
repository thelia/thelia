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

$fs = new \Symfony\Component\Filesystem\Filesystem();

$modules = [
    'Carousel',
    'Cheque',
    'Colissimo',
    'HookAnalytics',
    'HookSocial',
    'Tinymce',
];

foreach ($modules as $moduleCode) {
    $path = THELIA_MODULE_DIR.$moduleCode.DS.'AdminIncludes';

    if ($fs->exists($path)) {
        try {
            $fs->remove($path);
        } catch (Exception $e) {
            $message = sprintf(
                $this->trans('The update cannot delete the folder : "%s". Please delete this folder manually.'),
                $path
            );
            $this->log('warning', $message);
            $this->setMessage($message, 'warning');
        }
    }
}
