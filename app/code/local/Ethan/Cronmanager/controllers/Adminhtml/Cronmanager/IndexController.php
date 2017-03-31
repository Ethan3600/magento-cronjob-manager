<?php

class Ethan_Cronmanager_Adminhtml_Cronmanager_IndexController extends Mage_Adminhtml_Controller_Action
{
	protected function _isAllowed(){
		return true;
	}
	
	public function indexAction()
	{
		$block = $this->getLayout()->createBlock('cronmanager/adminhtml_index');
		$this->loadLayout()->_setActiveMenu('system');
		$this->_addContent($block);
		$this->renderLayout();
	}
	
	public function editAction()
	{
		$itemId = $this->getRequest()->getParam('id');
		$item = Mage::getModel('cron/schedule')->load($itemId);
	
		if(!!$item->getId()){
			Mage::register('cron_schedule_item', $item);
		}
	
		$this->loadLayout()->_setActiveMenu('system');
		$this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
		$this->_addContent($this->getLayout()->createBlock('cronmanager/adminhtml_edit'));
		$this->renderLayout();
	}
	
	public function newAction()
	{
		Mage::register('remove_dispatch_button', true);
		$this->_forward("edit", null, null, array('create' => true));
	}
	
	public function deleteAction()
	{
		$id = $this->getRequest()->getParam('id');
		$cronJob = Mage::getModel('cron/schedule')->load($id, 'schedule_id');
		$cronJob->delete();
		$this->_redirect('*/*/index');
	}
	
	public function saveAction()
	{
		$params = $this->getRequest()->getParams();
		try 
		{
			if(isset($params['id']))
			{
				$cronJob = Mage::getModel('cron/schedule')->load($params['id']);
				$scheduledAt = $this->_validateScheduledTime($params['scheduled_at']);
				if (is_array($scheduledAt))
				{
					$cronJob->setScheduledAt($scheduledAt[0]);
					$cronJob->save();
					$this->_redirect('*/*/index');
					$this->_getSession()->addWarning($scheduledAt['warning']);
					return;
				}
				$cronJob->setScheduledAt($scheduledAt);
				$cronJob->save();
				$this->_redirect('*/*/index');
				$this->_getSession()->addSuccess("Your Cron Job was successfully edited.");
				return;
				
			}
			$cronJob = Mage::getModel('cron/schedule');
			$cronJob->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING);
			$cronJob->setJobCode($params['job_code']);
			$scheduledAt = $this->_validateScheduledTime($params['scheduled_at']);
			$cronJob->setCreatedAt(now());
			if (is_array($scheduledAt))
			{
				$cronJob->setScheduledAt($scheduledAt[0]);
				$cronJob->save();
				$this->_redirect('*/*/index');
				$this->_getSession()->addWarning($scheduledAt['warning']);
				return;
			}
			$cronJob->setScheduledAt($scheduledAt);
			$cronJob->save();
			$this->_redirect('*/*/index');
			$this->_getSession()->addSuccess("Your Cron Job was successfully created.");
			return;
		}
		catch (Exception $e)
		{
			Mage::log($e->getMessage, null, 'cronmanager.log');
			$this->_redirect('*/*/new');
			$this->_getSession()->addError("There was an error saving your cron job. Please check that you've inserted a valid time.");
			return;
		}
		
	}
	
	public function flushAction()
	{
		$history = Mage::getModel('cron/schedule')->getCollection()
		->addFieldToFilter('status', array('in'=>array(
				Mage_Cron_Model_Schedule::STATUS_SUCCESS,
				Mage_Cron_Model_Schedule::STATUS_MISSED,
				Mage_Cron_Model_Schedule::STATUS_ERROR,
		)))
		->load();
		
		$historyLifetimes = array(
				Mage_Cron_Model_Schedule::STATUS_SUCCESS => Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_SUCCESS)*60,
				Mage_Cron_Model_Schedule::STATUS_MISSED => Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_FAILURE)*60,
				Mage_Cron_Model_Schedule::STATUS_ERROR => Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_HISTORY_FAILURE)*60,
		);
		
		$now = time();
		foreach ($history->getIterator() as $record) {
			if (strtotime($record->getExecutedAt()) < $now-$historyLifetimes[$record->getStatus()]) {
				$record->delete();
			}
		}
		
		// save time history cleanup was ran with no expiration
		Mage::app()->saveCache(time(), Mage_Cron_Model_Observer::CACHE_KEY_LAST_HISTORY_CLEANUP_AT, array('crontab'), null);
		$this->_redirect('*/*/index');
		return $this;
	}

	public function dispatchAction()
	{
		$scheduleId = $this->getRequest()->getParam('id');
		$schedule = Mage::getModel('cron/schedule')->load($scheduleId);
		$jobsRoot = Mage::getConfig()->getNode('crontab/jobs');
		$jobConfig = $jobsRoot ->{$schedule->getJobCode()};
		$isAlways = false;
		
		$runConfig = $jobConfig->run;
		
		$errorStatus = Mage_Cron_Model_Schedule::STATUS_ERROR;
		try 
		{
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
	
		} 
		catch (Exception $e) 
		{
			$schedule->setStatus($errorStatus)
			->setMessages($e->__toString());
			$this->_redirect("*/*/edit/id/$scheduleId");
			Mage::log($e->getMessage(),null,'cronmanager.log');
			$this->_getSession()->addError("There was an error dispatching this cron job.");
			return;
		}
		$schedule->save();
		$this->_redirect("*/*/edit/id/$scheduleId");
		$this->_getSession()->addSuccess("Cron Job successfully dispatched.");
		return;
	}
	
	public function massDeleteAction()
	{
		$cronJobIds = $this->getRequest()->getParam('cronmanager');
		if (!is_array($cronJobIds)) {
			$this->_getSession()->addError($this->__('Please select product(s).'));
		} else {
			if (!empty($cronJobIds)) {
				try {
					foreach ($cronJobIds as $cronJobId) {
						$cronJob = Mage::getSingleton('cron/schedule')->load($cronJobId);
						$cronJob->delete();
					}
					$this->_getSession()->addSuccess(
							$this->__('Total of %d record(s) have been deleted.', count($cronJobIds))
							);
				} catch (Exception $e) {
					$this->_getSession()->addError($e->getMessage());
				}
			}
		}
		$this->_redirect('*/*/index');
	}
	
	public function massDispatchAction()
	{
		$cronJobIds = $this->getRequest()->getParam('cronmanager');
		if (!is_array($cronJobIds)) 
		{
			$this->_getSession()->addError($this->__('Please select product(s).'));
		} 
		else 
		{
			if (!empty($cronJobIds)) 
			{
				try 
				{
					foreach ($cronJobIds as $cronJobId) 
					{
						$schedule = Mage::getSingleton('cron/schedule')->load($cronJobId);
						$jobsRoot = Mage::getConfig()->getNode('crontab/jobs');
						$jobConfig = $jobsRoot ->{$schedule->getJobCode()};
						$isAlways = false;
						
						$runConfig = $jobConfig->run;
						
						$errorStatus = Mage_Cron_Model_Schedule::STATUS_ERROR;
						
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
						
						$schedule->save();
					}
					$this->_getSession()->addSuccess(
							$this->__('Total of %d record(s) have been dispatched.', count($cronJobIds))
							);
				} 
				catch (Exception $e) 
				{
					$this->_getSession()->addError($e->getMessage());
				}
			}
		}
		$this->_redirect('*/*/index');
	}
	
	// =============================================== UTILITIES =============================================== //
	
	private function _validateScheduledTime($timeStr)
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
	
}