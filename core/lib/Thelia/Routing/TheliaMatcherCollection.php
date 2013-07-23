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
namespace Thelia\Routing;

use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * Collection of Matcher.
 *
 * Matcher resolve request into controller::method
 * exemple of Matcher : UrlMatcher of HttpKernel component (but it implements UrlMatcherInterface and not RequestMatcherInterface.
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaMatcherCollection implements RequestMatcherInterface, RequestContextAwareInterface
{
    protected $context;
    protected $matchers = array();
    protected $defaultMatcher;

    protected $sortedMatchers = array();

    /**
     * Constructor
     *
     * Check if this constructor is needed (is RequestContext needed ? )
     */
    public function __construct()
    {
        $this->context = new RequestContext();
    }

    /**
     * allow to add a matcher routing class to the matchers collection
     * matcher must implement RequestMatcherInterface
     *
     * priority can be fixed with $priority parameter
     *
     * @param RequestMatcherInterface $matcher
     * @param int                     $priority set the priority of the added matcher
     *
     */
    public function add(RequestMatcherInterface $matcher, $priority = 0)
    {
        if (!is_object($matcher)) {
            $matcher = new $matcher();
        }

        if (!isset($this->matchers[$priority])) {
            $this->matchers[$priority] = array();
        }

        $this->matchers[$priority][] = $matcher;
        $this->sortedMatchers = array();
    }

    /**
     *
     * Sort Matchers by priority
     *
     * @return array Array of matchers sorted by priority.
     */
    public function getSortedMatchers()
    {
        if (empty($this->sortedMatchers)) {
            $this->sortedMatchers = $this->sortMatchers();
        }

        return $this->sortedMatchers;
    }

    /**
     *
     * Sort the matcher by priority
     *
     * @return array Array of matchers sorted by priority.
     */
    public function sortMatchers()
    {
        $sortedMatchers = array();
        krsort($this->matchers);

        foreach ($this->matchers as $matcher) {
            $sortedMatchers = array_merge($sortedMatchers,$matcher);
        }

        return $sortedMatchers;
    }

    /**
     * Tries to match a request with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param Request $request The request to match
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If no matching resource could be found
     * @throws MethodNotAllowedException If a matching resource was found but the request method is not allowed
     */
    public function matchRequest(Request $request)
    {
        if (empty($this->matchers)) {
            throw new \InvalidArgumentException('there is no matcher added to the TheliaMatcherCollection');
        }

        foreach ($this->getSortedMatchers() as $matcher) {
            try {
                return $matcher->matchRequest($request);
            } catch (ResourceNotFoundException $e) {
                //no action, wait for next matcher
            } catch (MethodNotAllowedException $e) {
                /**
                 * @todo what todo with a MethodNotAllowedException ?
                 */
            }
        }

        throw new ResourceNotFoundException('No one matcher in this collection matched the current request');
    }

    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     *
     */
    public function setContext(RequestContext $context)
    {
        $this->context = $context;

    }

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     *
     */
    public function getContext()
    {
        return $this->context;
    }
}
