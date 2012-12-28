<?php

namespace Thelia\Database\NotORM;

class Result extends \NotORM_Result
{
    protected function query($query, $parameters) {
        if ($this->notORM->debug) {
            if (!is_callable($this->notORM->debug)) {
                $debug = "$query;";
                if ($parameters) {
                        $debug .= " -- " . implode(", ", array_map(array($this, 'quote'), $parameters));
                }
                $pattern = '(^' . preg_quote(dirname(__FILE__)) . '(\\.php$|[/\\\\]))'; // can be static
                foreach (debug_backtrace() as $backtrace) {
                        if (isset($backtrace["file"]) && !preg_match($pattern, $backtrace["file"])) { // stop on first file outside NotORM source codes
                                break;
                        }
                }
                fwrite(STDERR, "$backtrace[file]:$backtrace[line]:$debug\n");
            } elseif (call_user_func($this->notORM->debug, $query, $parameters) === false) {
                return false;
            }
        }
        
        if($this->notORM->logger !== false)
        {
            $this->notORM->logger->debug($query);
            $this->notORM->logger->debug($parameters);
        }
        $return = $this->notORM->connection->prepare($query);
        if (!$return || !$return->execute(array_map(array($this, 'formatValue'), $parameters))) {
            $this->notORM->logger->fatal("Error for this query : ".$query);
            $this->notORM->logger->fatal($this->notORM->errorCode());
            $this->notORM->logger->fatal(print_r($this->notORM->errorInfo(), true));
            $this->notORM->logger->fatal(print_r($return->errorInfo(), true));
                return false;
        }
        return $return;
    }
}