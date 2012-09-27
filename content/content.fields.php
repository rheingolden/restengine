<?php
/**
 * @package content
 */
/**
 * The AjaxTranslate page is used for translating strings on the fly
 * that are used in Symphony's javascript
 */
require_once (EXTENSIONS . '/restengine/lib/class.restengine.php');
require_once (EXTENSIONS . '/restengine/lib/class.restresource.php');
require_once(TOOLKIT . '/class.ajaxpage.php');

Class contentExtensionRestEngineFields extends AjaxPage{

	public function handleFailedAuthorisation(){
		$this->_status = 401;
		$this->_Result = json_encode(array('status' => __('You are not authorised to access this page.')));
	}

	public function view(){
		if(!is_numeric($this->_context[0])) {
			$this->_status = 400;
			$this->_Result = json_encode(array('error' => __('Invalid section ID.')));
		} else if (!$section = SectionManager::fetch((int) $this->_context[0]) && is_numeric($this->_context[0])){
			$this->_status = 404;
			$this->_Result =  json_encode(array('error' => __('The section specified does not exist.')));
		} else {
			$fields = array(array('id' => '0', 'title'=>'Use Entry ID Number'));
			foreach (ApiPage::getFieldObjects($this->_context[0]) as $field) {
				$fields[] = array('id' => $field->get('id'), 'title' => $field->get('label'));
			}
			$this->_Result = json_encode($fields);
		}
	}

	public function generate(){
		header('HTTP/1.1 ' . $this->_status . ' ' . RestEngine::getHTTPStatusMessage($this->_status));
		header('Content-Type: application/json');
		echo $this->_Result;
		exit;
	}

}

