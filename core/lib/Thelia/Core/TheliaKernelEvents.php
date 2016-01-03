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

namespace Thelia\Core;

/**
 * Class TheliaKernelEvents
 * @package Thelia\Core
 * @author manuel raynaud <manu@raynaud.io>
 */
final class TheliaKernelEvents
{
    const SESSION = "thelia_kernel.session";


    // -- Kernel Error Message Handle ---------------------------

    const THELIA_HANDLE_ERROR = "thelia_kernel.handle_error";
}
