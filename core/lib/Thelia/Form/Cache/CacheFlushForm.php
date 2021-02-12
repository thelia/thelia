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

namespace Thelia\Form\Cache;

use Thelia\Form\BaseForm;

/**
 * Class CacheFlushForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CacheFlushForm extends BaseForm
{
    /**
     * {@inheritdoc}
     */
    protected function buildForm()
    {
        //Nothing, we just want CSRF protection
    }

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return 'cache_flush';
    }
}
