<?php

class Ethan_Cronmanager_Block_Adminhtml_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
	public function __construct(){
		parent::__construct();
		$this->_objectId = 'schedule_id';
		$this->_blockGroup = 'cronmanager';
		$this->_controller = 'adminhtml';
		$this->_mode = 'edit';
		$cronId = $this->getRequest()->getParam('id');
		$dispatchUrl = $this->getUrl("*/*/dispatch", array('id'=> $cronId));
		$this->addButton('dispatch_cron', array(
				'label'     => "Dispatch",
				'onclick'   => "setLocation('$dispatchUrl')"
		));
		$removeDispatch = Mage::registry('remove_dispatch_button');
		if ($removeDispatch)
		{
			$this->removeButton('dispatch_cron');
		}
	}
	
	public function getHeaderText() {
		return Mage::helper('cronmanager')->__('Cron Info');
	}
	
	public function getBackUrl(){
		return $this->getUrl('*/*/index', array('_current'=>true));
	}
	
	public function getSaveUrl(){
		return $this->getUrl('*/*/save', array('_current'=>true));
	}
	
	public function getDeleteUrl(){
		return $this->getUrl('*/*/delete', array('_current'=>true));
	}
}
