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

namespace HookAdminHome;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

class HookAdminHome extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'hookadminhome';

    /** @var string */
    const ACTIVATE_NEWS = 'activate_home_news';

    /** @var string */
    const ACTIVATE_SALES= 'activate_home_sales';

    /** @var string */
    const ACTIVATE_INFO= 'activate_home_info';

    /** @var string */
    const ACTIVATE_STATS= 'activate_stats';

    public function getHooks()
    {
        return [
            [
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "hook_home_stats",
                "title" => "Hook Home Stats",
                "description" => "Hook to change default stats",
            ]
        ];
    }
}
