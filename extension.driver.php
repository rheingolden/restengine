<?php

require_once(EXTENSIONS . '/restengine/lib/class.restengine.php');
require_once(EXTENSIONS . '/restengine/lib/class.restresource.php');

Class extension_RestEngine extends Extension {

	private static $pageTypeString = 'RestEngine';
	private static $debugQSParam = '?XDEBUG_SESSION_START=16407';
	private $restAPITrigger = false;

	public function fetchNavigation() {
		return array(
			array(
				'location' => __('System'),
				'name' => __('RestEngine Settings'),
				'link' => '/settings/' . self::$debugQSParam
			)
		);
	}

	public function getSubscribedDelegates() {
		return array(
			/*Backend*/
			array(
				'page' => '/blueprints/events/new/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'appendEventFilter'
			),
			array(
				'page' => '/blueprints/events/edit/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'appendEventFilter'
			),
			/*Frontend*/
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendPageResolved',
				'callback' => 'checkPageType'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendParamsResolve',
				'callback' => 'addHTTPParams'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendProcessEvents',
				'callback' => 'processCRUDEvents'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'EventPreSaveFilter',
				'callback' => 'eventPreSaveFilter'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendOutputPostGenerate',
				'callback' => 'processTags'
			)
		);
	}


	/**
	 * TODO: Think about creating child object by POSTing to the parent.
	 * How that might integrate with the subsection manager?
	 * TODO: Restructure settings to have multiple sections => field relationships per page
	 * So you could have hierarchical data nested inside each other
	 * Projects page
	 * projects/category/single-project
	 * POSTing to projects would create a new category, POSTing to projects/category would create a new project
	 * How would the PUT logic be handled?
	 */

	//We want to map a field in a particular section to a parameter specified in the page.
	/**
	 * So we need to:
	 * 1. Get the section number from the event.
	 * 2. Use the section number to return a list of fields
	 * 3. Map a field to a parameter name for that page.
	 */


	public function install() {
		return Symphony::Database()->import("
			DROP TABLE IF EXISTS `tbl_restengine_fieldmaps`;
			CREATE TABLE `tbl_restengine_fieldmaps` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `page_id` int(11) unsigned NOT NULL,
			  `section_id` int(11) unsigned NOT NULL,
			  `field_id` int(11) unsigned NOT NULL,
			  `uid_parameter` varchar(255) NOT NULL,
			  `format_parameter` varchar(255),
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");
	}

	public function uninstall() {
		return Symphony::Database()->query("
			DROP TABLE IF EXISTS
				`tbl_restengine_fieldmaps`
		");
	}

	public function update($previousVersion) {
	}

	public function appendEventFilter(array $context) {
		$context['options'][] = array(
			'rest-fields-mismatch-fail',
			is_array($context['selected']) ? in_array('rest-fields-mismatch-fail', $context['selected']) : false,
			__('RestEngine: Fail on data mismatch between section fields and submitted data.')
		);
	}

	public function checkPageType($context) {
		if (is_array($context) &&
			is_array($context['page_data']) &&
			array_search(self::$pageTypeString, $context['page_data']['type']) !== false
		) {
			$this->restAPITrigger = true;
		}
		if ($this->restAPITrigger === true) {
			RestEngine::init();
			$page = $context['page_data'];
			switch (RestEngine::getHTTPMethod()) {
				case 'post':
					//TODO: we are replacing the events set not appending in. need to change that to not overwrite other events
					$page['events'] = 'restengine_post';
					break;
				case 'put':
					$page['events'] = 'restengine_post';
					break;
				case 'delete':
					//$page['events'] = 'restengine_delete';
					break;
			}
			$context['page_data'] = $page;
			RestEngine::setPageData($context['page_data']);
		}
	}

	public function addHTTPParams($context) {
		if ($this->restAPITrigger === true &&
			is_array($context) &&
			is_array($context['params'])
		) {
			$context['params']['http-method'] = XMLElement::stripInvalidXMLCharacters(RestEngine::getHttpMethod());
			$context['params']['http-accept'] = XMLElement::stripInvalidXMLCharacters(RestEngine::getHTTPAccept());
			$context['params']['put-content'] = XMLElement::stripInvalidXMLCharacters(RestEngine::getHTTPBodyContent());
		}
	}

	public function processCRUDEvents() {
		if ($this->restAPITrigger === true) {
			switch (RestEngine::getHTTPMethod()) {
				case 'post':
					break;
				case 'put':
					break;
				case 'delete':
					break;
			}
		}
	}

	public function eventPreSaveFilter($context) {
		if ($context['fields'] === null && (RestEngine::getHTTPMethod() === 'put' || 'post')) {
			$parsedData = RestEngine::parseBodyContent();
			if ($context['entry_id'] === null && RestEngine::getHTTPMethod() === 'put') {
				$pageID = RestEngine::getPageID();
				$urlParams = $this::getPageURLParams();
				//use the page ID to look up the format and uid param settings
				$settings = RestResourceManager::getByPageID($pageID);
				if(array_key_exists($settings['uid_parameter'], $urlParams)){
					$entryIDValue = $urlParams[$settings['uid_parameter']];

					if($settings['field_id'] == 0) {
						// 0 stands for using the Entry ID number directly so just
						// check to see if that entry number exists in the correct section
						$entrySection = EntryManager::fetchEntrySectionID($entryIDValue);
						if($entrySection == $settings['section_id']) {
							//good to go
							$entryID = $entryIDValue;
						}
					} else {
						$fieldType = FieldManager::fetchFieldTypeFromID($settings['field_id']);
						//TODO: Eventually add in a more robust field type management to distinguish between value and handle based fields if necessary, or do it by array searching?
						if($fieldType != 'memberemail') {
							$query = "SELECT `entry_id` FROM `tbl_entries_data_" . $settings['field_id'] . "` WHERE `handle` = '" . $entryIDValue . "' LIMIT 1";
						} else {
							$query = "SELECT `entry_id` FROM `tbl_entries_data_" . $settings['field_id'] . "` WHERE `value` = '" . $entryIDValue . "' LIMIT 1";
						}
						$entryID = Symphony::Database()->fetchVar('entry_id', 0, $query);
					}

					if(is_null($entryID)) {
						//no matching entry
						$context['messages'][] = array('restengine:invalid-id', FALSE, __('The specified resource "%1$s" does not exist.', array($entryIDValue)));
					} else {
						//good to go
						$context['entry_id'] = $entryID;
					}

				} else {

					$context['messages'][] = array('restengine:settings-error', FALSE, __('Invalid Rest Resource unique ID URL parameter: %1$s.', array($settings['uid_parameter'])));
					//probably some kind of error needs returning here
				}
			}

			if (is_array($parsedData)
				&& !empty($parsedData['data'])
				&& $parsedData['errors'] === null
			) {
				//Create the post data cookie element.
				General::array_to_xml($context['post_values'], $parsedData, true);
				//TODO: check for field mapping
				//TODO: Do we need to error when message body contains properties that we don't have fields for in the assigned section?
				$context['fields'] = $parsedData['data'];
			}
		}
	}

	public function processTags($output) {
		//Find the first special <restengine-status> tag and get its number value
		//Unsupported status code numbers will return 500 Internal Server Error.
		preg_match('#<restengine-status>(\d\d\d)</restengine-status>[\s?]#s', $output['output'], $statusTag);

		//Search for the first <restengine-headers> tag and get the tag contents as $headerTag variable for subsequent parsing
		preg_match('#<restengine-headers>.*?</restengine-headers>#s', $output['output'], $headerTags);

		//Delete the status code tag from frontend output if one was matched by the regex above
		//Set full HTTP Status code header with number from statusTag regex above
		if (count($statusTag) > 0) {
			$output['output'] = preg_replace('#' . preg_quote($statusTag[0], '#') . '#s', NULL, $output['output'], 1);
			header('HTTP/1.1 ' . $statusTag[1] . ' ' . RestEngine::getHTTPStatusMessage($statusTag[1]));
		}

		//If a set of special header tags are found by the regex above delete the tag from the output
		//and parse it into XML and create headers from the contents
		//or throw an error page if the string isn't valid XML.
		if (count($headerTags) > 0) {
			$output['output'] = preg_replace('#' . preg_quote($headerTags[0], '#') . '#s', NULL, $output['output'], 1);
			if (General::validateXML($headerTags[0], $errors, false)) {
				$headerTagsXML = new SimpleXMLElement($headerTags[0]);
				foreach ($headerTagsXML->header as $headerText) {
					switch ((string)$headerText['action']) {
						case "remove":
							header_remove($headerText);
							break;
						case "replace":
							header($headerText, true);
							break;
						default:
							header($headerText, false);
							break;
					}
				}
			} else {
				//If logged in as a Symphony admin/author show a more detailed error message
				if (Symphony::Engine()->isLoggedIn()) {
					$errorMessage = __('The following XML is not valid.') . '<p><code>' . General::sanitize($headerTags[0]) . '</code></p>' . __('Error %1$s', $errors);
				} else {
					//Otherwise show a generic message.
					$errorMessage = __('Invalid template HTTP header XML string.');
				}
				throw new SymphonyErrorPage(
					$errorMessage,
					__('RestEngine Internal Server Error')
				);
			}
		}
	}

	/*
	 * Utility Functions
	 * */

	public static function getPageURLParams() {
		$params = Frontend::instance()->Page()->Env();
		$url = $params['url'];
		return $url;
	}

	public static function baseURL() {
		return SYMPHONY_URL . '/extension/restengine/';
	}

	public static function pageType() {
		return self::$pageTypeString;
	}

}