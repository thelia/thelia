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

namespace Thelia\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Type\BaseType;
use TheliaSmarty\Template\Plugins\TheliaLoop;

/**
 * Give information about the given loop.
 * This information is the arguments names, types, default value, mandatory status, examples of use and enum possible value.
 *
 * Class LoopInfoCommand
 *
 * @author Matthias Nordest
 */
class LoopInfoCommand extends ContainerAwareCommand
{
    private TheliaLoop $theliaLoop;

    public function __construct(TheliaLoop $theliaLoop)
    {
        parent::__construct();
        $this->theliaLoop = $theliaLoop;
    }

    protected function configure(): void
    {
        $this
            ->setName('loop:info')
            ->setDescription('Displays arguments and possible enumeration values for the given loop')
            ->addArgument(
                'loop-name',
                InputArgument::OPTIONAL,
                'Name of the wanted loop'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Display all loop arguments and possible enumeration values in json format (mainly for parsing)'
            )
        ;
    }

    /**
     * @throws \ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('all')) {
            $this->getAllLoopsInfo();
        } else {
            $loopName = $input->getArgument('loop-name');
            if ($loopName === null) {
                trigger_error("'loop-name' argument or 'all' option missing", \E_USER_ERROR);
            }
            $this->getOneLoopInfo($output, $loopName);
        }

        return 0;
    }

    /**
     * Manage the display of all loops info in json format.
     *
     * @throws \ReflectionException
     */
    protected function getAllLoopsInfo(): void
    {
        // We get the list of all loops
        $loops = $this->theliaLoop->getLoopList();
        $classes = [];
        $allArgs = [];
        $result = [];

        // Put all the classes in the array $classes and associate them with the loop names associated
        // (A same loop can have several names)
        foreach ($loops as $name => $class) {
            if (!isset($classes[$class])) {
                $classes[$class] = [$name];
            } else {
                $classes[$class][] = $name;
            }
        }

        // Put all the arguments of the loops in an array $parentClass
        // Put the parent class name in the $result array
        foreach ($classes as $class => $names) {
            $dynamicDefinitionWarning = '';
            $arguments = $this->getLoopArgs($class, $dynamicDefinitionWarning);
            $allArgs[$names[0]] = $arguments;
            $result[$names[0]]['warning'] = $dynamicDefinitionWarning;
            $parentClass = $this->getParentLoop($class);
            $result[$names[0]]['extends'] = $parentClass;
        }

        // For each loop
        foreach ($allArgs as $loop => $args) {
            // The array of all arrays of the enum possible value by arguments
            // This array of array is useful in case an argument is an enum type AND an enum list type
            $additionalArrays = [];
            // The array of all titles for each enum possible value
            $additionalTitle = [];
            $positionInAdditionalArrays = 0;

            // Each loop can have several arguments
            foreach ($args as $arg) {
                // Get all the argument types
                $reflectionClass = new \ReflectionClass($arg->type);
                $property = $reflectionClass->getProperty('types');
                $property->setAccessible(true);
                $types = $property->getValue($arg->type);

                if ($arg->mandatory) {
                    $mandatory = 'yes';
                } else {
                    $mandatory = '';
                }

                $formatedTypes = '';
                $nbTypes = 0;
                $argumentExample = '';
                $nbEnumExample = 0;

                // Each argument can hava several types
                foreach ($types as $type) {
                    $this->parseType($nbTypes, $formatedTypes, $type, $nbEnumExample, $argumentExample, $arg->name, $additionalArrays, $additionalTitle, $positionInAdditionalArrays);
                }

                if ($arg->default === false) {
                    $default = 'false';
                } else {
                    $default = $arg->default;
                }
                $result[$loop]['args'][$arg->name] = [$formatedTypes, $default, $mandatory, $argumentExample];
            }
            // Each loop can have several enumerable arguments
            foreach ($additionalArrays as $index => $additionalArray) {
                // Each enumerable argument can have several enum possible values
                foreach ($additionalArray as $enumPossibility) {
                    $result[$loop]['enums'][$additionalTitle[$index]][] = $enumPossibility;
                }
            }
        }
        print_r(json_encode($result));
    }

    /**
     * Return the detailed loop arguments.
     *
     * @param string $loopClass                The loop class file path
     * @param string $dynamicDefinitionWarning To report any warnings
     *
     * @throws \ReflectionException
     *
     * @return mixed All detailed loop arguments
     */
    protected function getLoopArgs(string $loopClass, string &$dynamicDefinitionWarning): mixed
    {
        $loop = new $loopClass();

        // Need to use a protected method to get the arguments details
        // Reflecting the class to modify the accessibility.
        $reflectionClass = new \ReflectionClass($loop);
        $method = $reflectionClass->getMethod('getArgDefinitions');
        $method->setAccessible(true);

        try {
            // Try to execute the method to get the arguments details
            $result = $method->invoke($loop);
        } catch (\Throwable $error1) {
            // If execution failed, there is a dynamically defined argument detail
            try {
                // A known case of failure is the not defined request stack
                // Try to define one
                $this->initRequest();
                $session = $this->getContainer()->get('request_stack')->getCurrentRequest()->getSession();
                $session
                    ->setLang(Lang::getDefaultLanguage())
                    ->setCurrency(Currency::getDefaultCurrency())
                ;
                $this->getContainer()->get('request')->setSession($session);

                // To access the request stack's private attribute
                $property = $reflectionClass->getProperty('requestStack');
                $property->setAccessible(true);
                $property->setValue($loop, $this->getContainer()->get('request_stack'));

                $result = $method->invoke($loop);

                error_log("[Info] One or more \033[4;33m".$loopClass."\033[0m arguments or enum possible values are dynamically defined, you need to be careful");
                $dynamicDefinitionWarning = 'One or more loop argument values are defined dynamically, you need to be careful.';
            } catch (\Throwable $error2) {
                // If execution fails again, the problem will not be repaired and the result will be an empty loop.
                $result = $loop;
                $dynamicDefinitionWarning = 'An error occur when trying to get the loop arguments.';

                // The program will display errors, but will not crash
                error_log("[\033[31mWarning\033[0m] Error while trying to get the \033[4;33m".$loopClass."\033[0m arguments.");
                error_log('[Hint] This is probably due to the lack of an element instantiation in the getArgDefinitions() function, which creates and retrieves the loop arguments.');
                error_log($error2);
            }
        }

        return $result;
    }

    /**
     * Return the parent loop name of the loop given in parameter.
     *
     * @param string $loopClass The loop class file path
     *
     * @return string The parent loop class file path
     */
    protected function getParentLoop(string $loopClass): string
    {
        $parentClass = get_parent_class(new $loopClass());

        return $parentClass !== false ? $parentClass : '';
    }

    /**
     * Manage the display of the loop given by parameter.
     *
     * @throws \ReflectionException
     */
    protected function getOneLoopInfo(OutputInterface $output, string $loopName): void
    {
        // Get the list of all loop in the project
        $loops = $this->theliaLoop->getLoopList();

        // Search for the loop name given in parameter to get the loop class file path
        foreach ($loops as $name => $class) {
            if ($loopName === $name) {
                $loopClass = $class;
                break;
            }
        }

        // If the loop doesn't exist in the project
        if (!isset($loopClass)) {
            trigger_error("The loop name '".$loopName."' doesn't correspond to any loop in the project.\n".
                "'php Thelia loop:list' can help you find the loop name you're looking for.", \E_USER_ERROR);
        }

        $dynamicDefinitionWarning = '';
        $arguments = $this->getLoopArgs($loopClass, $dynamicDefinitionWarning);

        // Output display
        echo "\033[1m\033[32m".$loopName." loop\033[0m\n\n";
        echo "\033[1m\033[4mArguments\033[0m\n";
        echo $dynamicDefinitionWarning."\n" ?? '';

        // An array to save all arguments and associated details
        $tableToSort = [];

        // An array to save the enum possible values
        $additionalArrays = [];
        // Another one to save the arguments relative to the previous enum possible values
        $additionalTitle = [];
        $positionInAdditionalArrays = 0;

        foreach ($arguments as $argument) {
            if ($argument->mandatory) {
                $mandatory = 'yes';
            } else {
                $mandatory = '';
            }

            // To access the protected attribute of the argument types
            $reflectionClass = new \ReflectionClass($argument->type);
            $property = $reflectionClass->getProperty('types');
            $property->setAccessible(true);
            $types = $property->getValue($argument->type);
            // To get the types list in string format
            $formatedTypes = '';
            $nbTypes = 0;
            $argumentExample = '';
            $nbEnumExample = 0;

            // There are one or many type foreach argument
            foreach ($types as $type) {
                $this->parseType($nbTypes, $formatedTypes, $type, $nbEnumExample, $argumentExample, $argument->name, $additionalArrays, $additionalTitle, $positionInAdditionalArrays);
            }

            if ($argument->default === false) {
                // If the default argument value is a Boolean false, no value is displayed
                // Conversion in string format
                $default = 'false';
            } else {
                $default = $argument->default;
            }

            $tableToSort[] = [$argument->name, $formatedTypes, $default, $mandatory, $argumentExample];
        }

        sort($tableToSort);

        // To get a table display of the arguments
        $table = new Table($output);
        foreach ($tableToSort as $line) {
            $table->addRow([$line[0], $line[1], $line[2], $line[3], $line[4]]);
        }
        $table
            ->setHeaders(['Name', 'Type', 'Default', 'Mandatory', 'Examples'])
            ->render()
        ;

        if ($additionalArrays != []) {
            echo "\n\033[1m\033[4mEnum possible values\033[0m\n";
        }

        $additionalTitleIterator = 0;
        // To get a table display of all the enum possible values
        // There may be several enumerable arguments for the loop
        foreach ($additionalArrays as $additionalArray) {
            $newTable = new Table($output);
            $newTable->setHeaders([$additionalTitle[$additionalTitleIterator]]);
            // There may also be several possible value foreach enumerable argument
            foreach ($additionalArray as $enumPossibility) {
                $newTable->addRow($enumPossibility);
            }
            ++$additionalTitleIterator;
            $newTable->render();
        }
    }

    /**
     * Manage the loop argument type analysis.
     *
     * @param int      $nbTypes                    Counts the number of types an argument has
     * @param string   $formatedTypes              The types list in string format
     * @param BaseType $type                       The argument type studied
     * @param int      $nbEnumExample              Counts the number of enumerable types an argument has
     * @param string   $argumentExample            The example of use deduced for each types of the argument
     * @param string   $argumentName               The argument name
     * @param array    $additionalArrays           An array to save the arrays of enum possible values
     * @param array    $additionalTitle            An array to save the arguments relative to the enum possible values
     * @param int      $positionInAdditionalArrays A way to know where the type is in the $additionalArrays to get an enum possible value for the $argumentExample
     *
     * @throws \ReflectionException
     */
    protected function parseType(int &$nbTypes, string &$formatedTypes, BaseType $type, int &$nbEnumExample, string &$argumentExample, string $argumentName, array &$additionalArrays, array &$additionalTitle, int &$positionInAdditionalArrays): void
    {
        ++$nbTypes;
        if ($nbTypes > 1) {
            // If there are several types for a common argument, we need to separate the elements them with 'or' or ','
            $formatedTypes .= ' or ';
            if (($type->getType() != 'Enum list type' && $type->getType() != 'Enum type') || $nbEnumExample <= 0) {
                $argumentExample .= ', ';
            }
        }
        $formatedTypes .= $type->getType();
        if (($type->getType() != 'Enum list type' && $type->getType() != 'Enum type') || $nbEnumExample <= 0) {
            $argumentExample .= $argumentName.'=';
        }

        if ($type->getType() == 'Enum list type' || $type->getType() == 'Enum type') {
            // If this is an enum type, we must generate a specific array to list all the enum possible values
            $this->enumArrayGenerator($additionalArrays, $additionalTitle, $type, $argumentName);
            if ($nbEnumExample <= 0) {
                // If there isn't any example for an enumerable type already
                // Add example with a possible enum value
                $argumentExample .= '"'.$additionalArrays[$positionInAdditionalArrays][0][0].'"';
                ++$positionInAdditionalArrays;
            }
            ++$nbEnumExample;
        } elseif ($type->getType() == 'Int type') {
            // Case-by-case treatment for each type to deduce an example
            $argumentExample .= '"2"';
        } elseif ($type->getType() == 'Int list type') {
            $argumentExample .= '"2", '.$argumentName.'="1,4,7"';
        } elseif ($type->getType() == 'Boolean type') {
            $argumentExample .= '"true"';
        } elseif ($type->getType() == 'Boolean or both type') {
            $argumentExample .= '"true", '.$argumentName.'="*"';
        } elseif ($type->getType() == 'Float type') {
            $argumentExample .= '"30.1"';
        } elseif ($type->getType() == 'Any type') {
            $argumentExample .= '"foo"';
        } elseif ($type->getType() == 'Any list type') {
            $argumentExample .= '"foo", '.$argumentName.'="foo,bar,baz"';
        } elseif ($type->getType() == 'Alphanumeric string type') {
            $argumentExample .= '"foo"';
        } elseif ($type->getType() == 'Alphanumeric string list type') {
            $argumentExample .= '"foo", '.$argumentName.'="foo,bar,baz"';
        } else {
            // If we don't recognize the type, there is no need to display the argument name in the examples section
            $argumentExample = preg_replace('/'.$argumentName.'=$/', ' ', $argumentExample);
            $argumentExample = preg_replace('/, /', ' ', $argumentExample);
        }
    }

    /**
     * Manage the generation of the enum possibles values arrays.
     *
     * @param array    $additionalArrays An array to save the arrays of enum possible values
     * @param array    $additionalTitle  An array to save the arguments relative to the enum possible values
     * @param BaseType $type             The argument type studied
     * @param string   $argumentName     The argument name
     *
     * @throws \ReflectionException
     */
    protected function enumArrayGenerator(array &$additionalArrays, array &$additionalTitle, BaseType $type, string $argumentName): void
    {
        // To access the type values protected attribute, where all the enum possible values are defined
        $reflectionClass = new \ReflectionClass($type);
        $property = $reflectionClass->getProperty('values');
        $property->setAccessible(true);
        $values = $property->getValue($type);

        // The new array to save the enum possible values of the studied enum type
        $newArray = [];

        // Specific treatment if the argument is an order enumerable to separate normal and reverse values
        if ($argumentName == 'order') {
            // An array to distinguish the normal order values
            $arrayNormal = [];
            // An array to distinguish the reverse order values
            $arrayReverse = [];

            // Sorting of each enum possible value according to whether they are normal or reverse
            foreach ($values as $value) {
                if (str_contains($value, '_reverse') || str_contains($value, '-reverse')) {
                    $arrayReverse[] = $value;
                } else {
                    $arrayNormal[] = $value;
                }
            }

            // Association of normal and reverse values
            // The $newArray which save the enum possible values will save pairs of normal-reverse values
            foreach ($arrayNormal as $normalValue) {
                $isReverseFind = false;
                foreach ($arrayReverse as $reverseValue) {
                    if ($normalValue.'_reverse' == $reverseValue || $normalValue.'-reverse' == $reverseValue) {
                        // If normal and reverse values correspond
                        $newArray[] = [$normalValue, $reverseValue];

                        // Search and delete the reverse value matched
                        $index = array_search($reverseValue, $arrayReverse);
                        if ($index !== false) {
                            unset($arrayReverse[$index]);
                        }
                        $isReverseFind = true;
                        break;
                    }
                }
                if (!$isReverseFind) {
                    // If no match were found, save of the normal value without any reverse one
                    $newArray[] = [$normalValue, ''];
                }
            }

            // Save of all the no matched reverse values without any normal one
            foreach ($arrayReverse as $reverseValue) {
                $newArray[] = ['', $reverseValue];
            }
        } else {
            foreach ($values as $value) {
                // Use of an array [$value] to enable the same treatment to be used for classic enums
                // and order enums with an array of normal-reverse pairs
                $newArray[] = [$value];
            }
        }
        sort($newArray);

        $additionalArrays[] = $newArray;
        $additionalTitle[] = $argumentName;
    }
}
