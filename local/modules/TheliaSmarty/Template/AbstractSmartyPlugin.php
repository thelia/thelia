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

namespace TheliaSmarty\Template;

/**
 *
 * The class all Smarty Thelia plugin should extend
 *
 * Class AbstractSmartyPlugin
 * @package TheliaSmarty
 */
abstract class AbstractSmartyPlugin implements SmartyPluginInterface
{
    const WRAPPED_METHOD_PREFIX = '__wrap__';

    /**
     * Explode a comma separated list in a array, trimming all array elements
     *
     * @return mixed:
     */
    protected function explode($commaSeparatedValues)
    {
        if (null === $commaSeparatedValues) {
            return [];
        }

        $array = explode(',', $commaSeparatedValues);

        if (array_walk(
            $array,
            function (&$item) {
            $item = strtoupper(trim($item));
            }
        )) {
            return $array;
        }

        return [];
    }

    /**
     * Get a function or block parameter value, and normalize it, trimming balnks and
     * making it lowercase
     *
     * @param  array $params  the parameters array
     * @param  mixed $name    as single parameter name, or an array of names. In this case, the first defined parameter is returned. Use this for aliases (context, ctx, c)
     * @param  mixed $default the defaut value if parameter is missing (default to null)
     * @return mixed the parameter value, or the default value if it is not found.
     */
    public function getNormalizedParam($params, $name, $default = null)
    {
        $value = $this->getParam($params, $name, $default);

        if (\is_string($value)) {
            $value = strtolower(trim($value));
        }
        return $value;
    }

    /**
     * Get a function or block parameter value
     *
     * @param  array $params  the parameters array
     * @param  mixed $name    as single parameter name, or an array of names. In this case, the first defined parameter is returned. Use this for aliases (context, ctx, c)
     * @param  mixed $default the defaut value if parameter is missing (default to null)
     * @return mixed the parameter value, or the default value if it is not found.
     */
    public function getParam($params, $name, $default = null)
    {
        if (\is_array($name)) {
            foreach ($name as $test) {
                if (isset($params[$test])) {
                    return $params[$test];
                }
            }
        } elseif (isset($params[$name])) {
            return $params[$name];
        }

        return $default;
    }

    /**
     * From Smarty 3.1.33, we cannot pass parameters by reference to plugin mehods, and declarations like the
     * following will throw the error "Warning: Parameter 2 to <method> expected to be a reference, value given",
     * because Smarty uses call_user_func_array() to call plugins methods.
     *
     *     public function categoryDataAccess($params, &$smarty)
     *
     * This method wraps the method call to prevent this error
     *
     * @param string $functionName the method name
     * @param mixed[] $args the method arguments
     *
     * @throws \BadMethodCallException if the method was not found in this class
     */
    public function __call($functionName, $args)
    {
        if (false !== strpos($functionName, self::WRAPPED_METHOD_PREFIX)) {
            $functionName = str_replace(self::WRAPPED_METHOD_PREFIX, '', $functionName);

            $params = isset($args[0]) ? $args[0] : [];
            $smarty = isset($args[1]) ? $args[1] : null;

            return $this->$functionName($params, $smarty);
        }

        throw new \BadMethodCallException("Smarty plugin method '$functionName' was not found.", $this, $functionName);
    }

    protected function compatibilityFunctionCaller($params, $smarty, $functionName)
    {
        return $functionName($params, $smarty);
    }

        /**
     * @return SmartyPluginDescriptor[] an array of SmartyPluginDescriptor
     */
    abstract public function getPluginDescriptors();
}
