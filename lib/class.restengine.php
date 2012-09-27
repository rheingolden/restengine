<?php

require_once(TOOLKIT . '/class.json.php');

Class RestEngine {

	private static $_pageData = NULL;
	private static $_http_method = NULL;
	private static $_http_accept = NULL;
	private static $_http_body_content = NULL;
	private static $_request_content_type = NULL;
	private static $_jsonRegex = '#((\{).*(\})|(\[).*(\]))#s';
	private static $_xmlRegex = '#^<.*((<?)\/.*>)$#s';

	public static function init() {
		self::$_http_method = strtolower($_SERVER['REQUEST_METHOD']);
		self::$_http_accept = strtolower($_SERVER['HTTP_ACCEPT']);
		self::$_request_content_type = $_SERVER['CONTENT_TYPE'] ? strtolower($_SERVER['CONTENT_TYPE']) : null;
		if (self::getHTTPMethod() === 'put' || self::getHTTPMethod() === 'post') {
			self::$_http_body_content = file_get_contents('php://input');
		}
	}

	/*
	 * Utility Functions
	 * */

	public static function parseBodyContent() {
		$content = self::getHTTPBodyContent();
		$contentTypeHeader = self::getRequestContentType(false);
		switch ($contentTypeHeader) {
			case 'json':
				if (preg_match(self::$_jsonRegex, $content, $json) === 1) {
					return self::parseJSON($json);
				} else {
					return array(
						'data' => null,
						'errors' => __('JSON incorrectly formatted.')
					);
				}
				break;
			case 'xml':
				if (preg_match(self::$_xmlRegex, $content, $matched) === 1) {
					//concordance
					//continue to attempt to parse xml
					//return if parsed ok otherwise throw exception page, or return an error array?
				} else {

				}
				break;
			case 'unsupported':
				// a content type header that isn't supported was provided, throw some kind of error?
				break;
			default:
				// a content type
				break;
		}
	}

	private static function parseJSON($json) {
		$data = json_decode($json[0], true);
		$errors = null;
		if (function_exists('json_last_error')) {
			if (json_last_error() !== JSON_ERROR_NONE) {
				$errors = self::getJSONErrorCode(json_last_error());
			}
		} else if (!$data) {
			$errors = __('JSON incorrectly formatted.');
		}
		return array(
			'data' => $data,
			'errors' => $errors
		);
	}

	private static function parseXML($xml) {
		//XML Parse here
	}

	/*
	 * Getter/Setter Functions
	 * */

	public static function setPageData($pageData) {
		self::$_pageData = $pageData;
	}

	public static function getPageData() {
		return self::$_pageData;
	}

	public static function getPageID() {
		return self::$_pageData['id'];
	}

	public static function getPageTitle() {
		return self::$_pageData['title'];
	}

	//TODO: Do we need this method any more?
	public static function getSectionID(){
		return 1;
	}

	/**
	 * Returns the HTTP verb as a lowercase string.
	 * @static
	 * @return string
	 */

	public static function getHTTPMethod() {
		return self::$_http_method;
	}

	/**
	 * Returns the value of the Accept header.
	 * @static
	 * @return string
	 */

	public static function getHTTPAccept() {
		return self::$_http_accept;
	}

	/**
	 * Returns the value of the Content-Type header.
	 *
	 * @static
	 * @param bool $full (optional)
	 *  Boolean passed to return the full text string of the header when true,
	 *  or a shortened version of compatible MIME type when false.
	 * @return string|null
	 *  Returns either the full text of the header value as a string,
	 *  or 'xml', 'json', or 'unsupported' for a header that isn't xml or json
	 *  or null if no header at all is sent with the request.
	 */

	public static function getRequestContentType($full = true) {
		if ($full === true) {
			return self::$_request_content_type;
		} else {
			if (self::$_request_content_type === null) {
				return null;
			} else if (preg_match('#(/json|/xml)#', self::$_request_content_type, $matched) === 1) {
				return ltrim($matched[0], '/');
			} else {
				return 'unsupported';
			}
		}
	}

	public static function getHTTPBodyContent() {
		return self::$_http_body_content;
	}

	/**
	 * Returns the HTTP status reason phrase for a given numeric status code.
	 * @static
	 * @param $statusCode
	 *  A 3 digit HTTP status code.
	 * @return string
	 *  Returns the English HTTP Status Reason-phrase associated with the numeric status code.
	 *  Returns an empty string if the number code is not in the status message index.
	 */

	public static function getHTTPStatusMessage($statusCode) {
		$httpStatusCodeMessages = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => '(Unused)',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I Am A Teapot',
			420 => 'Enhance Your Calm',
			429 => 'Too Many Requests',
			431 => 'Request Headers Fields Too Large',
			451 => 'Unavailable For Legal Reasons',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported'
		);
		return (isset($httpStatusCodeMessages[$statusCode])) ? $httpStatusCodeMessages[$statusCode] : '';
	}

	public static function getJSONErrorCode($code) {
		switch ($code) {
			case JSON_ERROR_NONE:
				return __('No errors.');
				break;
			case JSON_ERROR_DEPTH:
				return __('Maximum stack depth exceeded.');
				break;
			case JSON_ERROR_STATE_MISMATCH:
				return __('Underflow or the modes mismatch.');
				break;
			case JSON_ERROR_CTRL_CHAR:
				return __('Unexpected control character found.');
				break;
			case JSON_ERROR_SYNTAX:
				return __('Syntax error, malformed JSON.');
				break;
			case JSON_ERROR_UTF8:
				return __('Malformed UTF-8 characters, possibly incorrectly encoded.');
				break;
			default:
				return __('Unknown JSON error');
				break;
		}
	}
}