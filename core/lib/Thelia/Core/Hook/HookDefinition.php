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

namespace Thelia\Core\Hook;

/**
 * Class HookDefinition
 * @package Thelia\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookDefinition
{
    const BASE_CLASS = 'Thelia\Core\Hook\BaseHook';

    const RENDER_BLOCK_EVENT = 'Thelia\Core\Event\Hook\HookRenderBlockEvent';
    const RENDER_FUNCTION_EVENT = 'Thelia\Core\Event\Hook\HookRenderEvent';
}
