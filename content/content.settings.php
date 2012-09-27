<?php

require_once(TOOLKIT . '/class.administrationpage.php');
require_once(TOOLKIT . '/class.eventmanager.php');
//require_once(TOOLKIT . '/class.restresource.php');

Class contentExtensionRestEngineSettings extends AdministrationPage {

	public function __viewIndex() {
		$this->setPageType('table');
		$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('RestEngine Settings'))));

		$this->appendSubheading(__('RestEngine API Resources'), Widget::Anchor(
			__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/', __('Associate a Symphony page with a REST API section'), 'create button', NULL, array('accesskey' => 'c')
		));

		$pageMapping = RestResourceManager::fetch();
		//$pageMapping = array();

		$TableHead = array(
			array(__('Page'), 'col'),
			array(__('Resource Base URL'), 'col'),
			array(__('Section'), 'col'),
			array(__('Unique ID Field'), 'col'),
			array(__('Unique ID URL Parameter'), 'col'),
			array(__('Format URL Parameter'), 'col'),
		);

		$TableBody = array();

		if (!is_array($pageMapping) || empty($pageMapping)) {
			$TableBody = array(Widget::TableRow(
				array(Widget::TableData(__('There are currently no RestEngine API pages. Click Crete New above to add one.'), 'inactive', NULL, count($TableHead)))
			));
		} else {
			foreach ($pageMapping as $page) {
				$pageTd = Widget::TableData(Widget::Anchor(
					$page->get('page_title'), Administration::instance()->getCurrentPageURL() . 'edit/' . $page->get('id') . '/', null, 'content'));
				$resourceURL = Widget::TableData(Widget::Anchor($page->get('page_uri'), $page->get('page_uri'), null));
				$sectionTd = Widget::TableData($page->get('section_name'));
				$fieldTd = Widget::TableData($page->get('field_name'));
				$uidParamTd = Widget::TableData($page->get('uid_parameter'));
				$formatParamTd = Widget::TableData($page->get('format_parameter'));
				$TableBody[] = Widget::TableRow(array($pageTd, $resourceURL, $sectionTd, $fieldTd, $uidParamTd, $formatParamTd));
			}
		}

		$table = Widget::Table(
			Widget::TableHead($TableHead),
			NULL,
			Widget::TableBody($TableBody),
			'selectable'
		);

		$this->Form->appendChild($table);

		$tableActions = new XMLElement('div');
		$tableActions->setAttribute('class', 'actions');

		$options = array(
			0 => array(null, false, __('With Selected...')),
			1 => array('delete', false, __('Delete'), 'confirm'),
		);

		$tableActions->appendChild(Widget::Apply($options));
		$this->Form->appendChild($tableActions);
	}

	public function __viewNew() {
		$this->__viewEdit();
	}

	public function __viewEdit() {
		$isNew = true;
		//Check if the Api Page ID passed in the URL exists
		if ($this->_context[0] == 'edit') {
			$isNew = false;
			if (!$setting_id = $this->_context[1]) redirect(extension_RestEngine::baseURL() . 'settings/');
			if (!$existing = RestResourceManager::fetch($setting_id)) {
				throw new SymphonyErrorPage(__('The API Resource page you requested to edit does not exist.'), __('API Page not found'), 'error');
			}
		}
		Administration::instance()->Page->addScriptToHead(URL . '/extensions/restengine/assets/restengine.fields.js');

		//Page Alerts from forms
		if (isset($this->_context[2])) {
			switch ($this->_context[2]) {
				case 'saved':
					$this->pageAlert(
						__(
							'API Resource Setting updated at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Settings</a>',
							array(
								DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
								extension_RestEngine::baseURL() . 'settings/new/',
								extension_RestEngine::baseURL() . 'settings/'
							)
						),
						Alert::SUCCESS);
					break;

				case 'created':
					$this->pageAlert(
						__(
							'API Resource created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Roles</a>',
							array(
								DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
								extension_RestEngine::baseURL() . 'settings/new/',
								extension_RestEngine::baseURL() . 'settings/'
							)
						),
						Alert::SUCCESS);
					break;
			}
		}

		$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));
		if ($formHasErrors) $this->pageAlert(
			__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR
		);

		$this->setPageType('form');

		if ($isNew) {
			$this->setTitle(__('Symphony &ndash; RestEngine API Resource Settings'));
			$this->appendSubheading(__('Untitled'));

			$fields = array(
				'page_id' => null,
				'section_id' => null,
				'field_id' => null,
				'uid_parameter' => null,
				'format_parameter' => null
			);
		} else {
			$this->setTitle(__('Symphony &ndash; RestEngine API Resource Settings &ndash; ') . $existing->get('page_title'));
			$this->appendSubheading($existing->get('page_title'));

			if (isset($_POST['fields'])) {
				$fields = $_POST['fields'];
			} else {
				$fields = array(
					'page_id' => $existing->get('page_id'),
					'section_id' => $existing->get('section_id'),
					'field_id' => $existing->get('field_id'),
					'uid_parameter' => $existing->get('uid_parameter'),
					'format_parameter' => $existing->get('format_parameter'),
				);
			}
		}

		$this->insertBreadcrumbs(array(
			Widget::Anchor(__('RestEngine API Resources'), extension_RestEngine::baseURL() . 'settings/')
		));

		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings type-file');
		$fieldset->appendChild(new XMLElement('legend', __('API Resource Settings')));

		$pageLabel = Widget::Label(__('Page'));
		$pageLabel->appendChild(Widget::Select('fields[page_id]', ApiPage::getPageList($fields['page_id'])));

		$sectionLabel = Widget::Label(__('Section'));
		$sectionLabel->appendChild(Widget::Select('fields[section_id]', ApiPage::getSectionList($fields['section_id']), array('id' => 'section')));

		$fieldLabel = Widget::Label(__('Field to use as unique ID in resource URL'));
		$fieldLabel->appendChild(Widget::Select('fields[field_id]', ApiPage::getFieldList($fields['section_id'], $fields['field_id']), array('id' => 'field')));

		$uid_parameterLabel = Widget::Label(__('URL Parameter to use as unique ID in resource URL'));
		$uid_parameterLabel->appendChild(Widget::Input('fields[uid_parameter]', $fields['uid_parameter']));

		$format_parameterLabel = Widget::Label(__('Response Format URL Parameter (optional)'));
		$format_parameterLabel->appendChild(Widget::Input('fields[format_parameter]', $fields['format_parameter']));


		if (isset($this->_errors['page_id'])) {
			//TODO: fix this error wrapping bit
			$fieldset->appendChild(Widget::Error($pageLabel, $this->_errors['name']));
		} else {
			$fieldset->appendChildArray(array($pageLabel, $sectionLabel, $fieldLabel, $uid_parameterLabel, $format_parameterLabel));
		}

		$this->Form->appendChild($fieldset);

		$div = new XMLElement('div');
		$div->setAttribute('class', 'actions');
		$div->appendChild(Widget::Input('action[save]', __('Save Changes'), 'submit', array('accesskey' => 's')));

		if (!$isNew) {
			$deleteButton = new XMLElement('button', __('Delete'));
			$deleteButton->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this API Resource'), 'type' => 'submit', 'accesskey' => 'd'));
			$div->appendChild($deleteButton);

		}
		$this->Form->appendChild($div);

	}

	public function __actionNew() {
		return $this->__actionEdit();
	}

	public function __actionEdit() {
		if (array_key_exists('delete', $_POST['action'])) {
			return $this->__actionDelete($this->_context[1], extension_RestEngine::baseURL() . 'settings/');
		}

		if (array_key_exists('save', $_POST['action'])) {
			$isNew = ($this->_context[0] !== "edit");
			$fields = $_POST['fields'];

			if (!$isNew) {
				if (!$map_id = $this->_context[1]) {
					redirect(extension_RestEngine::baseURL() . 'settings/');
				}

				if (!$existing = RestResourceManager::fetch($map_id)) {
					throw new SymphonyErrorPage(__('The API Resource you requested to edit does not exist.'), __('Resource not found'), 'error');
				}
			}
			//TODO: Decide if I really want to require this or not? If not also change SQL
			$uid_parameter = trim($fields['uid_parameter']);
			if (strlen($uid_parameter) == 0) {
				$this->_errors['name'] = __('This is a required field');
				return false;
			}

			//TODO: Do we need to check for the existence of anything here?
			if ($isNew) {
			} else {
			}

			$data = array(
				'page_id' => $fields['page_id'],
				'section_id' => $fields['section_id'],
				'field_id' => $fields['field_id'],
				'uid_parameter' => $fields['uid_parameter'],
				'format_parameter' => $fields['format_parameter'],
			);
			if ($isNew) {
				if ($map_id = RestResourceManager::add($data)) {
					redirect(extension_RestEngine::baseURL() . 'settings/edit/' . $map_id . '/created/');
				}
			} else {
				if (RestResourceManager::edit($map_id, $data)) {
					redirect(extension_RestEngine::baseURL() . 'settings/edit/' . $map_id . '/saved/');
				}
			}
		}
	}

	public function __actionDelete() {
		return "foo";
	}


}
