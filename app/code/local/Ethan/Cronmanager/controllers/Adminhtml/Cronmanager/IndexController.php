<?php

class Ethan_Cronmanager_Adminhtml_Cronmanager_IndexController extends Mage_Adminhtml_Controller_Action
{
	protected function _isAllowed()
	{
		$magentoAccessAllowed = Mage::getSingleton('admin/session')->isAllowed('admin/system/cronmanager');
		if($magentoAccessAllowed)
		{
			return true;
		}
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
		$helper = Mage::helper('cronmanager');
		try 
		{
			if(isset($params['id']))
			{
				$cronJob = Mage::getModel('cron/schedule')->load($params['id']);
				$scheduledAt = $helper->validateScheduledTime($params['scheduled_at']);
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
			$scheduledAt = $helper->validateScheduledTime($params['scheduled_at']);
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
		try 
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
		catch (Exception $e)
		{
			Mage::throwException("There was an error flushing the cron jobs.  Please check the log for details");
			Mage::log($e->getMessage(), null, 'cronmanager.log');
		}
		
	}

	public function dispatchAction()
	{
		$helper = Mage::helper('cronmanager');
		$scheduleId = $this->getRequest()->getParam('id');
		$schedule = Mage::getSingleton('cron/schedule')->load($scheduleId);
		try 
		{
			$schedule = $helper->dispatchCron($scheduleId, $schedule);
		} 
		catch (Exception $e) 
		{
			$schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
			->setMessages($e->__toString());
			$schedule->save();
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
				try 
				{
					foreach ($cronJobIds as $cronJobId) 
					{
						try 
						{
							$cronJob = Mage::getSingleton('cron/schedule')->load($cronJobId);
							$cronJob->delete();
						}
						catch (Exception $e)
						{
							Mage::log("There was an error processing cron job ID: " . $cronJobId."\n". $e->getMessage(), null, 'cronmanager.log');
						}
					}
					$this->_getSession()->addSuccess(
							$this->__('Total of %d record(s) have been deleted.', count($cronJobIds))
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
	
	public function massDispatchAction()
	{
		$helper = Mage::helper('cronmanager');
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
					$errors = 0;
					foreach ($cronJobIds as $cronJobId) 
					{
						try 
						{
							$schedule = Mage::getSingleton('cron/schedule')->load($cronJobId);
							$schedule = $helper->dispatchCron($cronJobId, $schedule);
							$schedule->save();
						}
						catch (Exception $e)
						{
							$errors++;
							$schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
							->setMessages($e->__toString());
							$schedule->save();
							Mage::log("There was an error dispatching cron job ID: " . $cronJobId."\n". $e->getMessage(), null, 'cronmanager.log');
						}
					}
					if($errors < 1)
					{
						$this->_getSession()->addSuccess(
								$this->__('Total of %d cron(s) have been dispatched.', count($cronJobIds))
								);
					}
					else 
					{
						$this->_getSession()->addWarning(
								$this->__('Total of %d cron(s) have been dispatched, but %d crons were not dispatched correctly.', count($cronJobIds), $errors)
								);	
					}
					
				} 
				catch (Exception $e) 
				{
					$this->_getSession()->addError($e->getMessage());
				}
			}
		}
		$this->_redirect('*/*/index');
	}
	
}