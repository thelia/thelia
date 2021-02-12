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

namespace HookAdminHome;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

class HookAdminHome extends BaseModule
{
    /** @var string */
    public const DOMAIN_NAME = 'hookadminhome';

    /** @var string */
    public const ACTIVATE_NEWS = 'activate_home_news';

    /** @var string */
    public const ACTIVATE_SALES = 'activate_home_sales';

    /** @var string */
    public const ACTIVATE_INFO = 'activate_home_info';

    /** @var string */
    public const ACTIVATE_STATS = 'activate_stats';

    public function getHooks()
    {
        return [
            [
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'hook_home_stats',
                'title' => 'Hook Home Stats',
                'description' => 'Hook to change default stats',
            ],
        ];
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
