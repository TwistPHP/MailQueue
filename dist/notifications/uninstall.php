<?php


	Twist::framework()->package()->uninstall();

	//Optional Line: Add this line if you are uninstalling database tables
	Twist::framework()->package()->importSQL(sprintf('%s/Data/uninstall.sql',dirname(__FILE__)));

	//Optional Line: Add this line if you are removing all package settings
	Twist::framework()->package()->removeSettings();

	\Twist\Core\Models\ScheduledTasks::deletePackageTasks('mailqueue');

	/**
	 * Remove all Hooks for the package
	 */
	\Twist::framework()->hooks()->cancel('TWIST_MANAGER_ROUTE','mailqueue-manager',true);
	\Twist::framework()->hooks()->cancel('TWIST_MANAGER_MENU','mailqueue-manager-menu',true);
	\Twist::framework()->hooks()->cancel('TWIST_EMAIL_PREPROCESS','mailqueue',true);