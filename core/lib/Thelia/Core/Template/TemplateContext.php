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
 * This class stores the last rendered template information.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * Creation date: 15/04/2015 10:35
 */

namespace Thelia\Core\Template;

class TemplateContext
{
    /** @var String  */
    protected $name;

    /** @var array */
    protected $parameters;

    /**
     * @param string $name the template name, as passed to ParserInterface::render()
     * @param array $parameters the template parameters, as passed to ParserInterface::render()
     */
    public function __construct($name, $parameters)
    {
        $this->name = preg_replace('/\.html$/', '', $name);
        $this->parameters = $parameters;
    }

    /**
     * @return array the template parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @return string the template name, without the final '.html' extension.
     */
    public function getName()
    {
        return $this->name;
    }
}
