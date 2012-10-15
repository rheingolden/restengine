<?php

class RestResourceManager {

	public static $_cached = array();

	public static function add(array $data) {
		Symphony::Database()->insert($data, 'tbl_restengine_fieldmaps');
		$id = Symphony::Database()->getInsertID();
		return $id;
	}

	public static function edit($map_id, array $data) {
		if (is_null($map_id)) {
			return false;
		}
		Symphony::Database()->update($data, 'tbl_restengine_fieldmaps', "`id` = " . $map_id);
		return true;
	}

	public static function getExistingRestPages() {
		$restPages = Symphony::Database()->fetch('SELECT `page_id` FROM `tbl_restengine_fieldmaps` ORDER BY `id` ASC');
		$pagesFlat = array();
		foreach ($restPages as $page) {
			$pagesFlat[] = $page['page_id'];
		}
		return $pagesFlat;
	}

	public static function fetch($map_id = null) {
		$result = array();
		$return_single = is_null($map_id) ? false : true;

		if ($return_single) {
			if (in_array($map_id, array_keys(RestResourceManager::$_cached))) {
				return RestResourceManager::$_cached[$map_id];
			}
			if (!$mapSet = Symphony::Database()->fetch(sprintf("SELECT * FROM `tbl_restengine_fieldmaps` WHERE `id` = %d ORDER BY `id` ASC LIMIT 1", $map_id))) {
				return array();
			}
		} else {
			$mapSet = Symphony::Database()->fetch("SELECT * FROM `tbl_restengine_fieldmaps` ORDER BY `id` ASC");
		}

		foreach ($mapSet as $map_id) {
			if (!in_array($map_id['id'], array_keys(RestResourceManager::$_cached))) {
				//RestResourceManager::$_cached[$map_id['id']] = $map_id;
				RestResourceManager::$_cached[$map_id['id']] = new ApiPage($map_id);
			}
			$result[] = RestResourceManager::$_cached[$map_id['id']];
		}
		return $return_single ? current($result) : $result;
	}

	public static function getByPageID($pageId) {
		if (!is_numeric($pageId)){
			return null;
		} else {
			$pageSettings = Symphony::Database()->fetch(sprintf('SELECT * FROM `tbl_restengine_fieldmaps` WHERE `page_id` = %d LIMIT 1', (int) $pageId));
			return $pageSettings['0'];
		}
	}

}

class ApiPage {

	private $settings = array();

	/*
	 * We need to be able to:
	 * Take an ID for Page, Section and Field and return it a Name.
	 * And vice versa?
	 */

	public function __construct(array $settings) {
		$this->setArray($settings);
		$this->setPageDetails($settings['page_id']);
		$this->setSectionDetails($settings['section_id']);
		$this->setFieldDetails($settings['field_id']);
	}

	public static function getPageList($page_id = 0) {
		$pagesArray = PageManager::fetchPageByType(extension_RestEngine::pageType());
		$exclude = RestResourceManager::getExistingRestPages();
		$pageOptions = array();
		foreach ($pagesArray as $page) {
			$selectedPage = ($page_id == $page['id']) ? true : false;
			//Disable select options for pages that already are set, allowing the current page and any pages that aren't already set up to still be selected.
			$attr = (is_numeric(array_search($page['id'], $exclude)) && $page_id != $page['id'] ) ? array('disabled' => 'disabled') : null;
			$pageOptions[] = array($page['id'], $selectedPage, $page['title'], null, null, $attr);
		}
		return $pageOptions;
	}

	public function setPageDetails($page_id) {
		$page = PageManager::fetchPageByID($page_id);
		if (count($page) == 0) {
			$this->set('page_title', __('Invalid or missing Page'));
			$this->set('page_uri', __('Invalid or missing Page'));
		} else {
			$this->set('page_title', $page['title']);
			$this->set('page_uri', URL . '/' . $page['path'] . '/' . $page['handle']);
			$this->set('page_events', $page['events']);
			$this->set('page_params', $page['params']);
		}
	}

	public static function getSectionList($section_id = 0) {
		$sectionObjects = SectionManager::fetch();
		$sectionOptions = array();
		foreach ($sectionObjects as $section) {
			$selectedSection = ($section_id == $section->get('id')) ? true : false;
			$sectionOptions[] = array($section->get('id'), $selectedSection, $section->get('name'));
		}
		return $sectionOptions;
	}

	public function setSectionDetails($section_id) {
		$section = SectionManager::fetch($section_id);
		if (!isset($section)) {
			$this->set('section_name', '<span class="error">' . __('Invalid or missing section') . '</span>');
			$this->set('section_handle', '<span class="error">' . __('Invalid or missing section') . '</span>');
		} else {
			$this->set('section_id', $section->get('id'));
			$this->set('section_name', $section->get('name'));
			$this->set('section_handle', $section->get('handle'));
		}
	}

	public static function getFieldList($section_id = 0, $field_id = 0) {
		if ($section_id == 0) {
			return array(array(null, false, __('Please choose a section')));
		} else {
			//TODO: In a future release, may want to allow user to override the field type.
			$fieldObjects = self::getFieldObjects($section_id);
			$fieldOptions = self::getDefaultFieldSelectArray();;
			foreach ($fieldObjects as $field) {
				$selectedField = ($field->get('id') == $field_id) ? true : false;
				$fieldOptions[] = array($field->get('id'), $selectedField, $field->get('label'));
			}
			return $fieldOptions;
		}

	}

	public static function getFieldObjects($section_id){
		return FieldManager::fetch(null, $section_id, 'ASC', 'sortorder', null, null, " AND t1.required = 'yes' AND t1.type IN ('input', 'uniqueinput', 'memberemail', 'memberusername', 'date' ) ");
		//Memberemail only has vaue so we may need to have a special case for that...
	}

	public function setFieldDetails($field_id) {
		$field = FieldManager::fetch($field_id);
		if (!isset($field)) {
			$this->set('field_name', '<span class="error">' . __('Invalid or missing field') . '</span>');
			$this->set('field_name', '<span class="error">' . __('Invalid or missing field') . '</span>');
		} else {
			$this->set('field_id', $field->get('id'));
			$this->set('field_name', $field->get('label'));
		}

	}

	public static function getDefaultFieldSelectArray() {
		return array(array('0' => '0', '1'=> false, '2' => 'Use Entry ID Number'));
	}

	/**
	 * Given a `$name`, this function returns the setting for this API Page Settings object.
	 * If no setting is found, this function will return null.
	 * If `$name` is not provided, the entire `$this->settings` array will
	 * be returned.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function get($name = null) {
		if (is_null($name)) return $this->settings;

		if (!array_key_exists($name, $this->settings)) return null;

		return $this->settings[$name];
	}

	/**
	 * Given a `$name` and a `$value`, this will set it into the API Page Settings object
	 * `$this->settings` array. By default, `$name` maps to the `tbl_restengine_fieldmaps`
	 * column names.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function set($name, $value) {
		$this->settings[$name] = $value;
	}

	/**
	 * Convenience function to set an associative array without using multiple
	 * `set` calls. This function expects an associative array. Borrowed from the Members extension.
	 *
	 * @param array $array
	 */
	public function setArray(array $array) {
		foreach ($array as $name => $value) {
			$this->set($name, $value);
		}
	}

}