<?php
namespace Stanford\WellBirthday;
/** @var \Stanford\WellBirthday\WellBirthday $module **/


if(isset($_GET["email-body"])){
    echo $module->getProjectSetting("email-body");
    exit;
}

$result = $module->startCron();