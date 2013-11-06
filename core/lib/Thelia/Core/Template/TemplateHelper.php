<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template;

use Thelia\Model\ConfigQuery;

class TemplateHelper
{
    /**
     * This is a singleton

     */
    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance == null) self::$instance = new TemplateHelper();

        return self::$instance;
    }

    public function getActivePdfTemplate() {
        return new TemplateDefinition(
                ConfigQuery::read('active-pdf-template', 'default'),
                TemplateDefinition::PDF
        );
    }

    public function getActiveAdminTemplate() {
        return new TemplateDefinition(
                ConfigQuery::read('active-admin-template', 'default'),
                TemplateDefinition::BACK_OFFICE
        );
    }

    public function getActiveFrontTemplate() {
        return new TemplateDefinition(
                ConfigQuery::read('active-admin-template', 'default'),
                TemplateDefinition::FRONT_OFFICE
        );
    }

    public function getList($templateType) {

        $list = $exclude = array();

        if ($templateType == TemplateDefinition::BACK_OFFICE) {
            $baseDir = THELIA_TEMPLATE_DIR.TemplateDefinition::BACK_OFFICE_SUBDIR;
        }
        else if ($templateType == TemplateDefinition::PDF) {
            $baseDir = THELIA_TEMPLATE_DIR.TemplateDefinition::PDF_SUBDIR;
        }
        else {
            $baseDir = THELIA_TEMPLATE_DIR;

            $exclude = array(TemplateDefinition::BACK_OFFICE_SUBDIR, TemplateDefinition::PDF_SUBDIR);
        }

        // Every subdir of the basedir is supposed to be a template.
        $di = new \DirectoryIterator($baseDir);

        foreach ($di as $file) {
            // Ignore 'dot' elements
            if ($file->isDot() || ! $file->isDir()) continue;

            // Ignore reserved directory names
            if (in_array($file->getFilename()."/", $exclude)) continue;

            $list[] = new TemplateDefinition($file->getFilename(), $templateType);
        }

        return $list;
    }
}
