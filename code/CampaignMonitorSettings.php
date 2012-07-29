<?php

class CampaignMonitorSettings extends Extension {
	function extraStatics() {
		return array(
			'db' => array(
				'CMApi' => 'Varchar(255)'
			)
		);
	}

	function updateCMSFields(&$f) {
		$tab = 'Root.CampaignMonitorSettings';
		$f->addFieldToTab($tab,new TextField('CMApi','API Key for your Campaing Monitor account'));
	}
}
