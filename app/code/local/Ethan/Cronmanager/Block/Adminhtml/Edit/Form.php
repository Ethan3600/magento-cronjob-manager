<?php

class Ethan_Cronmanager_Block_Adminhtml_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
	protected function _prepareForm() {
		
		$create = $this->getRequest()->getParam('create');
		
		$form = new Varien_Data_Form(array(
				'id'        => 'edit_form',
				'action'    => $this->getData('action'),
				'method'    => 'post'
		));
	
		$fieldset = $form->addFieldset('cron_info', array('legend'=>Mage::helper('cronmanager')->__('Cron Job Info')));
	
		if (!$create)
		{
			$cronJobItem = Mage::registry('cron_schedule_item');
			if(!!$cronJobItem && !!$cronJobItem->getId())
			{
				$fieldset->addField('schedule_id', 'label', array(
						'label'     => Mage::helper('cronmanager')->__('ID'),
						'name'      => 'schedule_id',
						'readonly'	=> true
				));
			}
			
			$fieldset->addField('job_code', 'label', array(
					'label'     => Mage::helper('cronmanager')->__('Job Code'),
					'name'      => 'job_code',
					'required'	=> true,
					'readonly'	=> true
			));
			
			$fieldset->addField('status', 'label', array(
					'label'     => Mage::helper('cronmanager')->__('Status'),
					'name'      => 'status',
					'required'	=> true,
					'readonly'	=> true
			));
			
			if($cronJobItem->getStatus() == Mage_Cron_Model_Schedule::STATUS_ERROR)
			{
				$fieldset->addField('messages', 'label', array(
						'label'     => Mage::helper('cronmanager')->__('Error Message'),
						'name'      => 'messages',
				));
			}
			
			$fieldset->addField('created_at', 'label', array(
					'label'     => Mage::helper('cronmanager')->__('Created At'),
					'name'      => 'created_at',
					'required'	=> true,
					'readonly'	=> true
			));
			
			$fieldset->addField('scheduled_at', 'text', array(
					'label'     => Mage::helper('cronmanager')->__('Scheduled At'),
					'name'      => 'scheduled_at',
					'required'	=> true,
					'value'	=> 'queue',
					'note'		=> "<i>Example</i>: 2016-12-26 20:05:00 (Follow <b>STRICTLY!</b>)
					<br> If you'd like to append the cron to the end of the cron \"queue\", please set the field to: <b>queue</b><br>
					<b style=\"color:red\">NOTE:</b> If you choose to append the job at the end of the queue,
					the cron job will be added <i>1 hour</i> after the last scheduled cron job."
			));
			
			$dateObj = DateTime::createFromFormat('Y-m-d H:i:s', now());
			$timeStr = $dateObj->format('Y-m-d H:i:s');
			$fieldset->addField('time_now', 'note', array(
					'label'     => Mage::helper('cronmanager')->__('Time Now'),
					'text'		=> $timeStr,
					'note'		=> 'This is relative to the time zone that Magento is on (typically UTC). This is meant to assit you in scheduling a cron.'
			));
			
			
			//Use session date for failed save content, (usually validation failure, just not to lose user input)
			//Then try to load from object data from the registry
			if ( $cronData = Mage::getSingleton('adminhtml/session')->getcronmanagerItemFormData() ){
				$form->setValues($cronData);
				Mage::getSingleton('adminhtml/session')->setcronmanagerItemFormData(null);
			} elseif ( !!$cronJobItem && !!$cronJobItem->getId() ) {
				$form->setValues($cronJobItem->getData());
			}
		}
		
		// ============================================== NEW ============================================== //

		else 
		{
			$fieldset->addField('job_code', 'select', array(
					'label'     => Mage::helper('cronmanager')->__('Job Code'),
					'name'      => 'job_code',
					'values'	=> Mage::getModel('cronmanager/source_crons')->toOptionArray()
			));
				
			$fieldset->addField('scheduled_at', 'text', array(
					'label'     => Mage::helper('cronmanager')->__('Scheduled At'),
					'name'      => 'scheduled_at',
					'required'	=> true,
					'value'	=> 'queue',
					'note'		=> "<i>Example</i>: 2016-12-26 20:05:00 (Follow <b>STRICTLY!</b>)
					<br> If you'd like to append the cron to the end of the cron \"queue\", please set the field to: <b>queue</b><br>
					<b style=\"color:red\">NOTE:</b> If you choose to append the job at the end of the queue,
					the cron job will be added <i>1 hour</i> after the last scheduled cron job."
			));
			$dateObj = DateTime::createFromFormat('Y-m-d H:i:s', now());
			$timeStr = $dateObj->format('Y-m-d H:i:s'); 
			$fieldset->addField('time_now', 'note', array(
					'label'     => Mage::helper('cronmanager')->__('Time Now'),
					'text'		=> $timeStr,
					'note'		=> 'This is relative to the time zone that Magento is on (typically UTC). This is meant to assit you in scheduling a cron.'
			));
				
				
			//Use session date for failed save content, (usually validation failure, just not to lose user input)
			//Then try to load from object data from the registry
			if ( $cronData = Mage::getSingleton('adminhtml/session')->getcronmanagerItemFormData() ){
				$form->setValues($cronData);
				Mage::getSingleton('adminhtml/session')->setcronmanagerItemFormData(null);
			} elseif ( !!$cronJobItem && !!$cronJobItem->getId() ) {
				$form->setValues($cronJobItem->getData());
			}
		}
				
		
		$form->setUseContainer(true);
		$this->setForm($form);
		return parent::_prepareForm();
	}
	
}
