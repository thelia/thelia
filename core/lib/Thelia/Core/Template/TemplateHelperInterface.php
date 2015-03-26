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

namespace Thelia\Core\Template;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ConfigQuery;
use Thelia\Log\Tlog;
use Thelia\Core\Translation\Translator;

interface TemplateHelperInterface
{
    /**
     * @return TemplateDefinition
     */
    public function getActiveMailTemplate();

    /**
     * Check if a template definition is the current active template
     *
     * @param  TemplateDefinition $tplDefinition
     * @return bool               true is the given template is the active template
     */
    public function isActive(TemplateDefinition $tplDefinition);

    /**
     * @return TemplateDefinition
     */
    public function getActivePdfTemplate();

    /**
     * @return TemplateDefinition
     */
    public function getActiveAdminTemplate();

    /**
     * @return TemplateDefinition
     */
    public function getActiveFrontTemplate();

    /**
     * Returns an array which contains all standard template definitions
     */
    public function getStandardTemplateDefinitions();

    /**
     * Return a list of existing templates for a given template type
     *
     * @param int  $templateType the template type
     * @param string $base the template base (module or core, default to core).
     * @return TemplateDefinition[] of \Thelia\Core\Template\TemplateDefinition
     */
    public function getList($templateType, $base = THELIA_TEMPLATE_DIR);
}
