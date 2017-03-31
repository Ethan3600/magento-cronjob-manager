<?php 

class Ethan_Cronmanager_Block_Adminhtml_Index_Grid extends Mage_Adminhtml_Block_Widget_Grid {
	
	public function __construct(){
		parent::__construct();
		$this->setId('cronmanager');
	}
	
	protected function _getStore()
	{
		$storeId = (int) $this->getRequest()->getParam('store', 0);
		return Mage::app()->getStore($storeId);
	}
	
	// Collection would be need for the grid content
	protected function _prepareCollection(){
		$store = $this->_getStore();
		$collection = Mage::getModel('cron/schedule')->getCollection()
 		->addFieldToSelect('schedule_id')
 		->addFieldToSelect('job_code')
 		->addFieldToSelect('status')
 		->addFieldToSelect('created_at')
 		->addFieldToSelect('scheduled_at')
 		->addFieldToSelect('executed_at')
 		->addFieldToSelect('finished_at');
	
		
		$this->setCollection($collection);
	
		parent::_prepareCollection();
		return $this;
	}
	
	protected function _prepareColumns()
	{
		$this->addColumn('schedule_id',
				array(
						'header'=> Mage::helper('cronmanager')->__('Cron Job ID'),
						'width' => '50px',
						'type'  => 'number',
						'index' => 'schedule_id',
				));
		$this->addColumn('job_code',
				array(
						'header'=> Mage::helper('cronmanager')->__('Cron Job Name'),
						'index' => 'job_code',
				));
		$this->addColumn('status',
				array(
						'header'=> Mage::helper('cronmanager')->__('Status'),
						'index' => 'status',
				));
		$this->addColumn('created_at',
				array(
						'header'=> Mage::helper('cronmanager')->__('Created At'),
						'index' => 'created_at',
				));
		$this->addColumn('scheduled_at',
				array(
						'header'=> Mage::helper('cronmanager')->__('Scheduled At'),
						'index' => 'scheduled_at',
				));
		$this->addColumn('executed_at',
				array(
						'header'=> Mage::helper('cronmanager')->__('Executed At'),
						'index' => 'executed_at',
				));
		$this->addColumn('finished_at',
				array(
						'header'=> Mage::helper('cronmanager')->__('Finished At'),
						'index' => 'finished_at',
				));	
	
		return parent::_prepareColumns();
	}
	
	protected function _prepareMassaction()
	{
		$this->setMassactionIdField('cronmanager_mass_action');
		$this->getMassactionBlock()->setFormFieldName('cronmanager');
		$this->getMassactionBlock()->addItem('delete', array(
				'label'=> Mage::helper('cronmanager')->__('Delete'),
				'url'  => $this->getUrl('*/*/massDelete'),
				'confirm' => Mage::helper('cronmanager')->__('Are you sure?')
		));
		
		$this->getMassactionBlock()->addItem('dispatch', array(
				'label'=> Mage::helper('cronmanager')->__('Dispatch'),
				'url'  => $this->getUrl('*/*/massDispatch'),
				'confirm' => Mage::helper('cronmanager')->__('Are you sure?')
		));
	}
	
	public function getRowUrl($row){
		// Clicking on the row will go to this URL
		return $this->getUrl('*/*/edit', array('id'=>$row->getId()));
	
		// To make the rows not edit-able, just return false
		// return false;
	}
	
    
}