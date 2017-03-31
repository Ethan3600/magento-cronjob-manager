<?php
class Ethan_Cronmanager_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function validateScheduledTime($timeStr)
	{
		if ($timeStr === 'queue')
		{
			// return the last scheduled cronjob +1 hour
			$cronJobCollection = Mage::getModel('cron/schedule')->getCollection()
			->addFieldToFilter('status', array('eq' => Mage_Cron_Model_Schedule::STATUS_PENDING));
			$endOfQueueTime = $cronJobCollection->orderByScheduledAt()->getLastItem()->getScheduledAt();
			$endOfQueueTime = !is_null($endOfQueueTime) ? $endOfQueueTime : now();
			$dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $endOfQueueTime);
			$dateInterval = new DateInterval('P0Y0M0DT1H0M0S');
			$dateObj->add($dateInterval);
			return $dateObj->format('Y-m-d H:i:s');
				
		}
		$timeStr = htmlentities(trim($timeStr), ENT_QUOTES, 'UTF-8');
		$dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $timeStr);
		$nowObj = DateTime::createFromFormat('Y-m-d H:i:s', now());
		if ($dateObj !== false) {
			//valid time
			$dateDiff = ($nowObj > $dateObj);
			if ($dateDiff)
			{
				$warning = "Your Cron Job was saved successfully, but it seems to be scheduled in the past.  If this was not your intention, please try again.";
				return array($timeStr, 'warning' => $warning);
			}
			return $timeStr;
		}
		else{
			//invalid time
			Mage::throwException("Invalid time format");
		}
	
	}
	
	public function dispatchCron($cronJobId, $schedule = null)
	{
		if (is_null($schedule))
		{
			$schedule = Mage::getSingleton('cron/schedule')->load($cronJobId);
		}
		$jobsRoot = Mage::getConfig()->getNode('crontab/jobs');
		$jobConfig = $jobsRoot ->{$schedule->getJobCode()};
		$isAlways = false;
		
		$runConfig = $jobConfig->run;
		if ($runConfig->model) {
			if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, (string)$runConfig->model, $run))
			{
				Mage::throwException(Mage::helper('cronmanager')->__('Invalid model/method definition, expecting "model/class::method".'));
			}
			if (!($model = Mage::getModel($run[1])) || !method_exists($model, $run[2]))
			{
				Mage::throwException(Mage::helper('cronmanager')->__('Invalid callback: %s::%s does not exist', $run[1], $run[2]));
			}
			$callback = array($model, $run[2]);
			$arguments = array($schedule);
		}
		if (empty($callback))
		{
			Mage::throwException(Mage::helper('cronmanager')->__('No callbacks found'));
		}
		
		$schedule
		->setExecutedAt(strftime('%Y-%m-%d %H:%M:%S', time()))
		->save();
		
		call_user_func_array($callback, $arguments);
		
		$schedule
		->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
		->setFinishedAt(strftime('%Y-%m-%d %H:%M:%S', time()));
		return $schedule;
	}
	
}
