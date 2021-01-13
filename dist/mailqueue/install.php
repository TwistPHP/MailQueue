<?php

	Twist::framework()->package()->install();

	//Optional Line: Add this line if you are adding database tables
	Twist::framework()->package()->importSQL(sprintf('%s/Data/install.sql',dirname(__FILE__)));

	//Optional Line: Add this line if you are adding framework settings
	Twist::framework()->package()->importSettings(sprintf('%s/Data/settings.json',dirname(__FILE__)));

	\Twist\Core\Models\ScheduledTasks::createTask('Email Sending Queue (MailQueue)','1','packages/mailqueue/Crons/MailQueue.cron.php',0,'',true,'mailqueue');

	/**
	 * Setup the page and menu items in the manager
	 */
	\Twist::framework()->hooks()->register('TWIST_MANAGER_ROUTE','mailqueue-manager',dirname(__FILE__).'/Hooks/manager.php',true);
	\Twist::framework()->hooks()->register('TWIST_MANAGER_MENU','mailqueue-manager-menu',file_get_contents(dirname(__FILE__).'/Data/manager-menu.json'),true);//Add a new email send protocol to the system
	\Twist::framework()->hooks()->register('TWIST_EMAIL_PREPROCESS','mailqueue',array('model' => 'Packages\mailqueue\Models\Queue'),true);