<?php

include ("./_config/config.php");
require_once './vendor/autoload.php';

$view = ViewControllerFactory::getController($_GET["route"]);

$view->loadTemplate("template.phtml");