<?php

$fs = new \Symfony\Component\Filesystem\Filesystem();

$modules = [
    'Carousel',
    'Cheque',
    'Colissimo',
    'HookAnalytics',
    'HookSocial',
    'Tinymce'
];

foreach ($modules as $moduleCode) {
    $path = THELIA_MODULE_DIR . $moduleCode . DS . 'AdminIncludes';

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
