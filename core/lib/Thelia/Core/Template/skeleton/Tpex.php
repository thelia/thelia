<?php


class ##CLASS_NAME##
{
    
    protected $loop = array(
        "##KEY_LOOP##" => "##VALUE_LOOP##"
    );
    
    protected $filter = array(
        "##KEY_FILTER##" => "##VALUE_FILTER##"
    );
            
     
    public function getLoop($name)
    {
        if (isset($this->loop[$name])) {
            return $this->loop[$name];
        }
        
        throw new ResourceNotFound(sprintf("%s loop does not exists", $name));
    }
    
    public function getFilter($name)
    {
        if (isset($this->filter[$name])) {
            return $this->filter[$name];
        }
        
        throw new ResourceNotFound(sprintf("%s loop does not exists", $name));
    }
    
    
    
}
