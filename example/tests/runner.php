<?php

/**
 * @file
 * Assert ITLS pages are working.
 */

load_config('config/local');
//run_suite('suite_dev_only');
//run_suite('suite');
run_suite('javascript', ['js' => TRUE]);
