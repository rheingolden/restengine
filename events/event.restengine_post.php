<?php

if (!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

require_once(TOOLKIT . '/class.event.php');
require_once(EXTENSIONS . '/restengine/lib/class.restengine.php');

Class EventRestEngine_post extends SectionEvent {

	public $ROOTELEMENT = 'restengine-post-result';

	public $eParamFILTERS = array();

	public static function about() {
		return array(
			'name' => 'RestEngine POST (Create)',
			'author' => array(
				'name' => 'Kyle McGuire',
				'website' => 'http://kymcism.com',
				'email' => 'kyle@kymcism.com'),
			'version' => '0.1',
			'release-date' => '2012-05-27T17:58:00+07:00',
			'trigger-condition' => 'action[restengine_post]');
	}

	public static function getSource() {
		return RestEngine::getSectionID();
	}

	public static function allowEditorToParse(){
		return false;
	}

	public static function documentation() {
		return '';
	}

	public function load() {
		return $this->__trigger();
	}

}