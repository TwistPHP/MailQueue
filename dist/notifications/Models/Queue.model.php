<?php

	/**
	 * TwistPHP - An open source PHP MVC framework built from the ground up.
	 * Shadow Technologies Ltd.
	 *
	 * This program is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
	 *
	 * @author     Shadow Technologies Ltd. <contact@shadow-technologies.co.uk>
	 * @license    https://www.gnu.org/licenses/gpl.html GPL License
	 * @link       https://twistphp.com
	 */

	namespace Packages\mailqueue\Models;

	class Queue{

		protected $resTemplate = null;
		public $arrDebugLog = array();

		/**
		 * Queue an email into the mailqueue ready to be sent out in the background
		 * @param $resCreateModel
		 * @return bool
		 * @throws \Exception
		 */
		public static function emailPreProcess(&$resCreateModel){

			$arrEmailData = $resCreateModel->data();

			//Only queue an email that has not already been queued
			if(!array_key_exists('mailqueue',$arrEmailData)){

				$arrEmailData['mailqueue'] = true;

				$resQueue = \Twist::Database()->records(TWIST_DATABASE_TABLE_PREFIX.'mailqueue')->create();
				$resQueue->set('data',json_encode($arrEmailData));
				$resQueue->set('added',date('Y-m-d H:i:s'));
				$resQueue->commit();

				//Send back false as we dont want the send process to continue
				return false;
			}

			//The email has been queued before, skip and send
			return true;
		}

		/**
		 * Spools up a queue processor, each processor watches the queue for 50 seconds, after this point it
		 * finished up the current notification being processed and will then die. A cron should be running every
		 * minute to spool up a new queue processor.
		 * @throws \Exception
		 */
		public static function processor(){

			$arrSettings = array(
				'restricted' => \Twist::framework()->setting('MAILQUEUE_RESTRICTED'),
				'per_cycle' => \Twist::framework()->setting('MAILQUEUE_PER_CYCLE'),
				'cooldown_after' => \Twist::framework()->setting('MAILQUEUE_COOLDOWN_AFTER'),
				'queue_wait' => \Twist::framework()->setting('MAILQUEUE_QUEUE_WAIT'),
				'auto_retry' => \Twist::framework()->setting('MAILQUEUE_AUTO_RETRY'),
				'retry_limit' => \Twist::framework()->setting('MAILQUEUE_RETRY_LIMIT'),
			);

			//Mark items as failed that have been processing for more than 1 minute
			\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'failed', `error` = 'Failed to send after processing for +1 minute' WHERE `status` = 'processing' AND (`started` IS NULL OR `started` < DATE_SUB(NOW(), INTERVAL -1 MINUTE))",TWIST_DATABASE_TABLE_PREFIX);

			//Delete all the records marked as delete
			\Twist::Database()->records(TWIST_DATABASE_TABLE_PREFIX.'mailqueue')->delete('delete','status',null);

			//Update all the failed items that have reached their retry limit to be deleted on next run.
			\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'delete' WHERE  `send_attempts` >= %d",TWIST_DATABASE_TABLE_PREFIX,$arrSettings['retry_limit']);

			//Update all the sent items to be deleted on next run.
			\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'delete' WHERE `status` = 'sent'",TWIST_DATABASE_TABLE_PREFIX);

			//Update all the restricted items to be deleted on next run.
			\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'delete' WHERE `status` = 'restricted'",TWIST_DATABASE_TABLE_PREFIX);

			$intStart = time();
			$intRunningTimer = 0;

			//When auto retry is enabled the first cycle on any queue will retry failed notifications
			$strNextCycleProcess = ($arrSettings['auto_retry']) ? 'failed' : 'new';

			self::debug("Starting queue process for ".$arrSettings['cooldown_after']." seconds");

			//Allow a max running time of X seconds (This will minimise any overlap between the cron runs)
			while($intRunningTimer < $arrSettings['cooldown_after']){

				$arrNotifications = \Twist::Database()->records(TWIST_DATABASE_TABLE_PREFIX.'mailqueue')->find($strNextCycleProcess,'status',null,'ASC',$arrSettings['per_cycle']);
				$arrNotifications = \Twist::framework()->tools()->arrayReindex($arrNotifications,'id');

				//Sometimes we might start with failed notifications first, Ensure that all subsequent sends will be for new notifications
				$strNextCycleProcess = 'new';

				if(count($arrNotifications) == 0){
					self::debug("Queue Empty, Waiting (".$arrSettings['queue_wait']." seconds)...");
					sleep($arrSettings['queue_wait']);
				}else{
					\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'processing',`send_attempts` = `send_attempts` + 1, `started` = NOW() WHERE `id` IN (%s)",TWIST_DATABASE_TABLE_PREFIX,implode(',',array_keys($arrNotifications)));

					foreach($arrNotifications as $arrEachSend){

						try{

							$arrEmailData = json_decode($arrEachSend['data'],true);
							self::debug("- SEND: ".implode(', ',$arrEachSend['to']));

							$resEmail = \Twist::Email()->create();
							$resEmail->data($arrEmailData);
							$blStatus = $resEmail->send();

							//Log the result of the send
							$resQueue = \Twist::Database()->records(TWIST_DATABASE_TABLE_PREFIX.'mailqueue')->get($arrEachSend['id'],'id');
							if($blStatus){
								$resQueue->set('status','sent');
								$resQueue->set('sent',date('Y-m-d H:i:s'));
							}else{
								$resQueue->set('status','failed');
								$resQueue->set('error','Failed to send, method returned false');
							}
							$resQueue->commit();

						}catch(\Exception $exception){
							self::debug("- # FAILED #");
							\Twist::Database()->query("UPDATE `%smailqueue` SET `status` = 'failed', `error` = '%s'  WHERE `id` = %d",TWIST_DATABASE_TABLE_PREFIX,$arrEachSend['id'],$exception->getMessage());
						}
					}
				}

				//Get the total amount of seconds the script has been running for
				$intRunningTimer = time() - $intStart;
			}

			self::debug("Queue processes ended");
		}

		public static function clear($intNotificationID){

			$resNotification = \Twist::Database()->records(TWIST_DATABASE_TABLE_PREFIX.'mailqueue')->get($intNotificationID);

			if(\Twist::User()->loggedIn() && $resNotification->get('user_id') == \Twist::User()->currentID()){
				$resNotification->set('read','1');
				return $resNotification->commit();
			}

			return false;
		}

		public static function clearAll(){
			return \Twist::Database()->query("UPDATE `%smailqueue` SET `read` = '1' WHERE `user_id`= %d",
				TWIST_DATABASE_TABLE_PREFIX,
				\Twist::User()->currentID()
			)->status();
		}

		protected static function debug($strMessage){

			if(\Twist::framework()->setting('MAILQUEUE_DEBUG')){
				echo $strMessage."\n";
			}
		}
	}