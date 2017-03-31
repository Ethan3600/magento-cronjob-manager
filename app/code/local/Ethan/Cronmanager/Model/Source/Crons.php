<?php

class Ethan_Cronmanager_Model_Source_Crons
{
	public function toOptionArray()
	{
		$crons = array();
		$cronJobs = Mage::app()->getConfig()->getNode('crontab/jobs');
		
		foreach($cronJobs as $config => $jobs)
		{
			foreach($jobs as $job)
			{
				$crons[] = array(
						'label' => (string) $job->getName(),
						'value' => $job->getName()
				);
			}
		}

		return $crons;
	}
}
