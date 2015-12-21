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

/**
 * @author Franck Allimant <franck@cqfdev.fr>
 * Creation date: 26/03/2015 16:36
 */

namespace Thelia\Core\Template;

use Thelia\Model\ConfigQuery;

class TheliaTemplateHelper implements TemplateHelperInterface
{
    /**
     * @return TemplateDefinition
     */
    public function getActiveMailTemplate()
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-mail-template', 'default'),
            TemplateDefinition::EMAIL
        );
    }

    /**
     * Check if a template definition is the current active template
     *
     * @param  TemplateDefinition $tplDefinition
     * @return bool               true is the given template is the active template
     */
    public function isActive(TemplateDefinition $tplDefinition)
    {
        $tplVar = '';

        switch ($tplDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                $tplVar = 'active-front-template';
                break;
            case TemplateDefinition::BACK_OFFICE:
                $tplVar = 'active-admin-template';
                break;
            case TemplateDefinition::PDF:
                $tplVar = 'active-pdf-template';
                break;
            case TemplateDefinition::EMAIL:
                $tplVar = 'active-mail-template';
                break;
        }

        return $tplDefinition->getName() == ConfigQuery::read($tplVar, 'default');
    }
    /**
     * @return TemplateDefinition
     */
    public function getActivePdfTemplate()
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-pdf-template', 'default'),
            TemplateDefinition::PDF
        );
    }

    /**
     * @return TemplateDefinition
     */
    public function getActiveAdminTemplate()
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-admin-template', 'default'),
            TemplateDefinition::BACK_OFFICE
        );
    }

    /**
     * @return TemplateDefinition
     */
    public function getActiveFrontTemplate()
    {
        return new TemplateDefinition(
            ConfigQuery::read('active-front-template', 'default'),
            TemplateDefinition::FRONT_OFFICE
        );
    }

    /**
     * Returns an array which contains all standard template definitions
     */
    public function getStandardTemplateDefinitions()
    {
        return array(
            $this->getActiveFrontTemplate(),
            $this->getActiveAdminTemplate(),
            $this->getActivePdfTemplate(),
            $this->getActiveMailTemplate(),
        );
    }

    /**
     * Return a list of existing templates for a given template type
     *
     * @param  int $templateType the template type
     * @param string $base the template base (module or core, default to core).
     * @return TemplateDefinition[] of \Thelia\Core\Template\TemplateDefinition
     */
    public function getList($templateType, $base = THELIA_TEMPLATE_DIR)
    {
        $list = $exclude = array();

        $tplIterator = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach ($tplIterator as $type => $subdir) {
            if ($templateType == $type) {
                $baseDir = rtrim($base, DS).DS.$subdir;

                try {
                    // Every subdir of the basedir is supposed to be a template.
                    $di = new \DirectoryIterator($baseDir);

                    /** @var \DirectoryIterator $file */
                    foreach ($di as $file) {
                        // Ignore 'dot' elements
                        if ($file->isDot() || ! $file->isDir()) {
                            continue;
                        }

                        // Ignore reserved directory names
                        if (in_array($file->getFilename(), $exclude)) {
                            continue;
                        }

                        $list[] = new TemplateDefinition($file->getFilename(), $templateType);
                    }
                } catch (\UnexpectedValueException $ex) {
                    // Ignore the exception and continue
                }
            }
        }

        return $list;
    }
}
