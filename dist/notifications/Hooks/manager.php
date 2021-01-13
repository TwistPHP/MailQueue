<?php

	\Twist::define('MAILQUEUE_VIEWS',dirname(__FILE__).'/../Views');

	$this -> controller( '/mailqueue/%', 'Packages\mailqueue\Controllers\Manager' );