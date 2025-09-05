<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template;

use Symfony\Component\HttpFoundation\Request;

interface TemplateHelperInterface
{
    public function getActiveMailTemplate(): TemplateDefinition;

    /**
     * Check if a template definition is the current active template.
     *
     * @return bool true is the given template is the active template
     */
    public function isActive(TemplateDefinition $templateDefinition): bool;

    public function getActivePdfTemplate(): TemplateDefinition;

    public function getActiveAdminTemplate(): TemplateDefinition;

    public function getActiveFrontTemplate(): TemplateDefinition;

    /**
     * Returns an array which contains all standard template definitions.
     */
    public function getStandardTemplateDefinitions();

    /**
     * Return a list of existing templates for a given template type.
     *
     * @param int    $templateType the template type
     * @param string $base         the template base (module or core, default to core)
     *
     * @return TemplateDefinition[] of \Thelia\Core\Template\TemplateDefinition
     */
    public function getList(int $templateType, string $base = THELIA_TEMPLATE_DIR): array;

    public function isAdmin(?Request $request): bool;
}
