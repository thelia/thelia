<?php


namespace Thelia\Command\Output;


use Symfony\Component\Console\Output\ConsoleOutput;

class TheliaConsoleOutput extends ConsoleOutput{

    public function renderBlock(array $messages, $style = "info")
    {
        $strlen = function ($string) {
            if (!function_exists('mb_strlen')) {
                return strlen($string);
            }

            if (false === $encoding = mb_detect_encoding($string)) {
                return strlen($string);
            }

            return mb_strlen($string, $encoding);
        };
        $length = 0;
        foreach ($messages as $message) {
            $length = ($strlen($message) > $length) ? $strlen($message) : $length;
        }
        $ouput = array();
        foreach ($messages as $message) {
            $output[] = "<" . $style . ">" . "  " . $message . str_repeat(' ', $length - $strlen($message)) . "  </" . $style . ">";
        }

        $this->writeln($output);
    }

}