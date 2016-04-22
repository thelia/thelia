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

// Check php version before doing anything else
if (version_compare(PHP_VERSION, '5.5', '<')) {
    die("Your server is running PHP ".PHP_VERSION.". Thelia 2 requires PHP 5.5 or better.");
}
