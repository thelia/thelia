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
            $arguments = $this->getLoopArgs($class);
            $allArgs[$names[0]] = $arguments;
            $parentClass = $this->getParentLoop($class);
            $result[$names[0]]['extends'] = [$parentClass];
        }

        foreach ($allArgs as $loop => $args) {
            foreach ($args as $arg) {
                // The array of all arrays of the enum possible value by arguments
                $additionalArrays = [];
                // The array of all titles for each enum possible value
                $additionalTitle = [];
                $positionInAdditionalArrays = 0;

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
                foreach ($types as $type) {
                    $this->parseType($nbTypes, $formatedTypes, $type, $nbEnumExample, $argumentExample, $arg->name, $additionalArrays, $additionalTitle, $positionInAdditionalArrays);
                    for ($i = 0; $i < \count($additionalTitle); ++$i) {
                        foreach ($additionalArrays as $additionalArray) {
                            foreach ($additionalArray as $enumPossibility) {
                                $result[$loop]['enums'][$additionalTitle[$i]][] = $enumPossibility;
                            }
                        }
                    }
                }
                if ($arg->default === false) {
                    $default = 'false';
                } else {
                    $default = $arg->default;
                }
                $result[$loop]['args'][$arg->name] = [$formatedTypes, $default, $mandatory, $argumentExample];
            }
        }
        print_r(json_encode($result));
    }

    /**
     * Return the detailed loop arguments.
     *
     * @throws \ReflectionException
     */
    protected function getLoopArgs(string $loopClass): mixed
    {
        $loop = new $loopClass();

        // That's hideous, but it works.
        // We need to use a protected method, so we reflect the class to modify it.
        $reflectionClass = new \ReflectionClass($loop);
        $method = $reflectionClass->getMethod('getArgDefinitions');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($loop);
        } catch (\Throwable $e) {
            $result = $loop;
            error_log("[\033[31mWarning\033[0m] Error while trying to get the ".$loopClass.' arguments.');
            error_log('[Hint] This is probably due to the lack of an element instantiation in the getArgDefinitions() function, which creates and retrieves the loop arguments.');
            error_log($e);
        }

        return $result;
    }

    /**
     * Return the parent loop name of the loop given in parameter.
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
        $loops = $this->theliaLoop->getLoopList();
        foreach ($loops as $name => $class) {
            if ($loopName === $name) {
                $loopClass = $class;
                break;
            }
        }
        if (!isset($loopClass)) {
            trigger_error("The loop name '".$loopName."' doesn't correspond to any loop in the project.\n".
                "'php Thelia loop:list' can help you find the loop name you're looking for.", \E_USER_ERROR);
        }
        $arguments = $this->getLoopArgs($loopClass);

        echo "\033[1m\033[32m".$loopName." loop\033[0m\n\n";

        echo "\033[1m\033[4mArguments\033[0m\n";

        $tableToSort = [];
        $table = new Table($output);

        $additionalArrays = [];
        $additionalTitle = [];

        $positionInAdditionalArrays = 0;

        foreach ($arguments as $argument) {
            $reflectionClass = new \ReflectionClass($argument->type);
            $property = $reflectionClass->getProperty('types');
            $property->setAccessible(true);
            $types = $property->getValue($argument->type);
            if ($argument->mandatory) {
                $mandatory = 'yes';
            } else {
                $mandatory = '';
            }
            $formatedTypes = '';
            $nbTypes = 0;
            $argumentExample = '';
            $nbEnumExample = 0;
            foreach ($types as $type) {
                $this->parseType($nbTypes, $formatedTypes, $type, $nbEnumExample, $argumentExample, $argument->name, $additionalArrays, $additionalTitle, $positionInAdditionalArrays);
            }

            if ($argument->default === false) {
                $default = 'false';
            } else {
                $default = $argument->default;
            }
            $tableToSort[] = [$argument->name, $formatedTypes, $default, $mandatory, $argumentExample];
        }

        sort($tableToSort);
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

        $additionnalTables = [];
        foreach ($additionalArrays as $additionalArray) {
            $newTable = new Table($output);
            foreach ($additionalArray as $enumPossibility) {
                $newTable->addRow($enumPossibility);
            }

            $additionnalTables[] = $newTable;
        }
        for ($i = 0; $i < \count($additionnalTables); ++$i) {
            $additionnalTables[$i]
                ->setHeaders([$additionalTitle[$i]])
                ->render()
            ;
        }
    }

    /**
     * Manage the loop argument type analysis.
     */
    protected function parseType(int &$nbTypes, &$formatedTypes, $type, &$nbEnumExample, &$argumentExample, string $argumentName, array &$additionalArrays, array &$additionalTitle, int &$positionInAdditionalArrays): void
    {
        ++$nbTypes;
        if ($nbTypes > 1) {
            $formatedTypes .= ' or ';
            if (!(($type->getType() == 'Enum list type' || $type->getType() == 'Enum type') && $nbEnumExample > 0)) {
                $argumentExample .= ', ';
            }
        }
        $formatedTypes .= $type->getType();
        if (!(($type->getType() == 'Enum list type' || $type->getType() == 'Enum type') && $nbEnumExample > 0)) {
            $argumentExample .= $argumentName.'=';
        }

        // If this is an enum type, we must generate a specific table
        if ($type->getType() == 'Enum list type' || $type->getType() == 'Enum type') {
            $this->enumArrayGenerator($additionalArrays, $additionalTitle, $type, $argumentName);
            if ($nbEnumExample <= 0) {
                $argumentExample .= '"'.$additionalArrays[$positionInAdditionalArrays][0][0].'"';
                ++$positionInAdditionalArrays;
            }
            ++$nbEnumExample;
        } elseif ($type->getType() == 'Int type') {
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
     * Manage the generation of the enum type possibles values arrays.
     *
     * @throws \ReflectionException
     */
    protected function enumArrayGenerator(array &$additionalArrays, array &$additionalTitle, mixed $type, string $argumentName): void
    {
        $reflectionClass = new \ReflectionClass($type);
        $property = $reflectionClass->getProperty('values');
        $property->setAccessible(true);
        $values = $property->getValue($type);
        $newArray = [];
        if ($argumentName == 'order') {
            $arrayNormal = [];
            $arrayReverse = [];
            $arrayOrder = [];
            foreach ($values as $value) {
                if (str_contains($value, '_reverse') || str_contains($value, '-reverse')) {
                    $arrayReverse[] = $value;
                } else {
                    $arrayNormal[] = $value;
                }
            }
            foreach ($arrayNormal as $normalValue) {
                $isReverseFind = false;
                foreach ($arrayReverse as $reverseValue) {
                    if ($normalValue.'_reverse' == $reverseValue || $normalValue.'-reverse' == $reverseValue) {
                        $arrayOrder[] = [$normalValue, $reverseValue];
                        $index = array_search($reverseValue, $arrayReverse);
                        if ($index !== false) {
                            unset($arrayReverse[$index]);
                        }
                        $isReverseFind = true;
                        break;
                    }
                }
                if (!$isReverseFind) {
                    $arrayOrder[] = [$normalValue, ''];
                }
            }
            foreach ($arrayReverse as $reverseValue) {
                $arrayOrder[] = ['', $reverseValue];
            }
            sort($arrayOrder);
            foreach ($arrayOrder as $order) {
                $newArray[] = $order;
            }
        } else {
            sort($values);
            foreach ($values as $value) {
                $newArray[] = [$value];
            }
        }
        $additionalArrays[] = $newArray;
        $additionalTitle[] = $argumentName;
    }
}
