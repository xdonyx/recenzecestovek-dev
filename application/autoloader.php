<?php

spl_autoload_register(function ($class_name) { 

	global $config;

	$path = ($config["applicationBaseUrl"]);

	if (file_exists($path . '/controllers/' . $class_name . '.php')) {
		include $path . '/controllers/' . $class_name . '.php';
	} else if (file_exists($path . '/controllers/view/' . $class_name . '.php')) {
		include $path . '/controllers/view/' . $class_name . '.php';
	} else if (file_exists($path . '/controllers/api/' . $class_name . '.php')) {
		include $path . '/controllers/api/' . $class_name . '.php';
	}

	else
    	include $path . "/application/" . $class_name . '.php';
});