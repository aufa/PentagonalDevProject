<?php
/**
 * Must be Defined Root
 */
if (!defined('ROOT')) {
    return;
}

/*
 * --------------------------------------------------------------------
 * LOAD THE BOOTSTRAP FILE
 * --------------------------------------------------------------------
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Determinator.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'FunctionAlternate.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Processor.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Hook.php';

Processor::run();
