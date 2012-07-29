<?php

class CampaignMonitorForm extends Page {
	static $db = array(
		'APIKey' => 'Varchar(255)',
		'ListID' => 'Varchar(50)',
		'SuccessText' => 'Varchar(255)',
		'FieldForSegment' => 'Varchar(50)',
		'SegmentValue' => 'Varchar(50)',
	);

	static $has_one = array(
		'ConfirmationPage' => 'Page'
	);

	static $icon = 'CampaignMonitor/images/CampaignMonitorForm';

	static $default_fields = array(
		array(
			'FieldName' => 'Name',
			'Key' => '[Name]',
			'DataType' => 'Text',
			'FieldOptions' => array()
		),
		array(
			'FieldName' => 'Email',
			'Key' => '[Email]',
			'DataType' => 'Email',
			'FieldOptions' => array()
		)
	);

	function getCMSFields() {
		$f = parent::getCMSFields();
		$tab = 'Root.Content.FormOptions';
		$f->addFieldToTab($tab, new HeaderField('CMSettings','API settings for your Account and List'));
		$f->addFieldToTab($tab, new TextField('APIKey','Your account API key (will inherit from the siteconfig if left blank)'));
		$f->addFieldToTab($tab, new TextField('ListID','List ID (required to display form)'));
		$f->addFieldToTab($tab, new HeaderField('SegmentSettings','Segment Settings (optional)',3));
		$f->addFieldToTab($tab, new TextField('FieldForSegment','Field Key to use for segment (defaults to "Segment")'));
		$f->addFieldToTab($tab, new TextField('SegmentValue','Value to submit for segment'));//maybe code into dropdown, if reflective of actual segment field, through ajax call
		$f->addFieldToTab($tab, new HeaderField('GenSettings','General settings for this form'));
		$f->addFieldToTab($tab, new TextField('SuccessText','Message displayed after successful submission (optional)'));
		$f->addFieldToTab($tab, new TreeDropdownField('ConfirmationPageID','Page to redirect to on confirmation (returns to form if left blank)','SiteTree'));
		return $f;
	}
}

class CampaignMonitorForm_Controller extends Page_Controller {
	static $field_conversion = array(
		'Text' => 'TextField',
		'Email' => 'EmailField',
		'Number' => 'NumericField',
		'MultiSelectOne' => 'DropdownField',//code for flag for optionset
		'MultiSelectMany' => 'CheckboxSetField',
		'Date' => 'DateField',
		'Country' => 'DropdownField',
		'USState' => 'DropdownField'
	);

	function Form() {
		$f = $this->getCMFieldset();
		$a = new Fieldset(
			new FormAction('subscribeUser','Subscribe')
		);
		$v = new RequiredFields('name','email');
		$form = new Form(
			$this,
			'Form',
			$f,
			$a,
			$v
		);
		return $form;//*/
	}

	function subscribeUser($d,$f) {
		$n = $d['Name'];
		$e = $d['Email'];
		unset($d['Email'],$d['Name']);
		$cmfields = isset($d['CMFields']) ? explode(',',$d['CMFields']) : false;
		if(!$cmfields) return false;
		$cmfields = array_combine($cmfields,$cmfields);
		$data = array_intersect_key($d,$cmfields);
		$custom = array();
		foreach($data as $k => $v) {
			if(is_array($v)) {
				foreach($v as $val) {
					$custom[] = array(
						'Key' => $k,
						'Value' => $val
					);
				}
			} else {
				$custom[] = array(
					'Key' => $k,
					'Value' => $v
				);
			}
		}
		$api = $this->APIKey();
		$listid = $this->ListID;
		$subs = new CS_REST_Subscribers($listid,$api);
		$res = $subs->add(array(
			'EmailAddress' => $e,
			'Name' => $n,
			'CustomFields' => $custom,
			'Resubscribe' => true
		));
		if($res->was_successful()) {
			$mess = $this->SuccessText ? $this->SuccessText : false;
			Session::set('Message',$mess);
			if($id = $this->ConfirmationPageID) {
				$page = DataObject::get_by_id('SiteTree',$id);
				Director::redirect($page->Link());
			} else {
				Director::redirectBack();
			}
		} else {
			Session::set('Message',"We're sorry, an error appears to have occured during your submission, please try again");
			Director::redirectBack();
		}
		return;
	}

	function getCMFieldset() {
		$fields = CampaignMonitorForm::$default_fields;
		$api = $this->APIKey();
		if(!$api) {
			trigger_error('API key must be set on either SiteConfig or individual form');
			return false;
		}
		$listid = $this->ListID;
		if(!$listid) {
			trigger_error('List ID must be set on individual form');
			return false;
		}
		$list = new CS_REST_Lists($listid,$api);
		$result = $list->get_custom_fields();
		if($result->was_successful()) {
			$obj_fields = $result->response;
			foreach($obj_fields as $o) {
				$fields[] = array(
					'FieldName' => $o->FieldName,
					'Key' => $o->Key,
					'DataType' => $o->DataType,
					'FieldOptions' => $o->FieldOptions
				);
			}
			$seg = $this->FieldForSegment ? $this->FieldForSegment : 'Segment';
			$cmfields = '';
			$fs = new Fieldset();
			foreach($fields as $f) {
				$n = $f['FieldName'];
				$k = substr($f['Key'],1,-1);
				$cmfields .= $k.',';
				if($k == $seg) {
					$type = 'HiddenField';
				} else {
					$type = isset(self::$field_conversion[$f['DataType']]) ? self::$field_conversion[$f['DataType']] : 'TextField';
				}
				$options = count($f['FieldOptions']) ? $f['FieldOptions'] : false;
				$o = '';
				if($options && ($type != 'HiddenField')) {
					$o = array(
						'' => '--Select Your '.$n.'--'
					);
					foreach($options as $option) {
						$o[$option] = $option;
					}
				} elseif ($type == 'HiddenField') {
					$o = $this->SegmentValue ? $this->SegmentValue : false;
				}
				$field = new $type($k,$n,$o);
				$fs->push($field);
			}
			$cmfields = substr($cmfields,0,-1);
			$fs->push(new HiddenField('CMFields','CMFields',$cmfields));
			return $fs;
		} else {
			trigger_error('HTTP Status Code: '.$result->http_status_code.' - Failed to retrieve list fields, please check he API key or List ID that is saved');
			return false;
		}
	}


	function APIKey() {
		$api = $this->APIKey;
		if(!$api) {
			$sc = SiteConfig::current_site_config();
			$api = $sc->CMApi;
		}
		return $api ? $api : false;
	}
}
