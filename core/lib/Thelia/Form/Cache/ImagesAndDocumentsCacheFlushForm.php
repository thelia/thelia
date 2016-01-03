<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Form\Cache;

use Thelia\Form\BaseForm;

/**
 * Class CacheFlushForm
 * @package Thelia\Form\Cache
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ImagesAndDocumentsCacheFlushForm extends BaseForm
{
    /**
     * @inheritdoc
     */
    protected function buildForm()
    {
        //Nothing, we just want CSRF protection
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return "images_and_documents_cache_flush";
    }
}
