<?php

if(!function_exists('autoload_custom')){
    function autoload_custom($class)
    {
        if (strpos($class, 'CI_') !== 0)
        {
            if(file_exists(APPPATH . 'core/' . $class . '.php')){
                require_once(APPPATH . 'core/' . $class . '.php');
            }
        }
    }

    spl_autoload_register('autoload_custom');
}