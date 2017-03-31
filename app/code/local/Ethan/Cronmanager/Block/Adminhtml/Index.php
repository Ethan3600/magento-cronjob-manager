<?php

class Ethan_Cronmanager_Block_Adminhtml_Index extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct()
	{
		$this->_blockGroup = 'cronmanager';
		$this->_controller = 'adminhtml_index';
		$this->_headerText = Mage::helper('cronmanager')->__('Cron Jobs');
		$flushUrl = $this->getUrl('*/*/flush');
		$this->addButton("clean_cronjobs", array(
				'label'     => "Flush Cron Jobs",
				'onclick'   => "setLocation('$flushUrl')"
		));
		parent::__construct();
	}
}
