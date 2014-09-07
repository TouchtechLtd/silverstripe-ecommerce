<?php
/**
 * turns a field into a geo-coding field.
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: forms
 * @inspiration: http://gmaps-samples-v3.googlecode.com/svn/trunk/places/autocomplete-addressform.html
 **/

class EcommerceGeocodingField extends TextField {

	/**
	 *
	 * @var Boolean
	 */
	protected $useSensor = true;

	/**
	 * Do you want this annoying ... this website wants to know exactly where you are
	 * and what you are wearing thing ... this is your VAR.
	 * @param Boolean
	 */
	public function setUseSensor($b) {$this->useSensor = $b;}

	/**
	 * JS file used to run this field
	 * @var String
	 */
	protected $googleSourceJS = "//maps.google.com/maps/api/js?libraries=places";

	/**
	 *
	 * @param String
	 */
	public function setGoogleSourceJS($s) {$this->googleSourceJS = $s;}

	/**
	 * JS file used to run this field
	 * @var String
	 */
	protected $jsLocation = "ecommerce/javascript/EcomEcommerceGeocodingField.js";

	/**
	 *
	 * @param String
	 */
	public function setJsLocation($s) {$this->jsLocation = $s;}

	/**
	 * CSS file used in this field (can be themed!)
	 * @var String
	 */
	protected $cssLocation = "EcommerceGeocodingField";

	/**
	 *
	 * @param String
	 */
	public function setCssLocation($s) {$this->cssLocation = $s;}

	/**
	 * list of links between
	 * form fields in the current field (e.g. TextField with name City)
	 * and the result XML.
	 * When the results are returned this field will fill the form
	 * fields with XML data from the results using this array
	 * Format is:
	 * [formFieldName] => array(
	 *   resultType1 => 'long_name',
	 *   resultType2 => 'long_name',
	 *   resultType2 => 'short_name',
	 *   etc...
	 * )
	 * e.g.
	 * <code php>
	 *     "BillingRegion" => array("administrative_area_level_1" => "long_name", "country" => "short_name")
	 * </code>
	 * @var Array
	 */
	protected $fieldMap = array();

	/**
	 *
	 * @param Array
	 */
	public function setFieldMap($a) {$this->fieldMap = $a;}

	/**
	 *
	 * @param String $formField
	 * @param Array $arrayOfGeoData
	 */
	public function addFieldMapEntry($formField, $arrayOfGeoData) {$this->fieldMap[$formField] = $arrayOfGeoData;}

	/**
	 *
	 * @param String $formField
	 */
	public function removeFieldMapEntry($formField) {unset($this->fieldMap[$formField]);}

	/**
	 *
	 * @return Array
	 */
	public function getFieldMap() {return $this->fieldMap;}

	/**
	 * @return Boolean
	 */
	function hasData() { return false; }

	/**
	 * @return string
	 */
	function Field($properties = array()) {
		if($this->useSensor) {
			$this->googleSourceJS .= "&sensor=true";
		}
		Requirements::javascript($this->googleSourceJS);
		Requirements::javascript($this->jsLocation);
		Requirements::customScript($this->getJavascript(), "EcommerceGeocodingField".$this->id());
		if($this->cssLocation) {
			Requirements::themedCSS($this->cssLocation, "ecommerce");
		}
		$this->setAttribute("autocomplete", "off");
		return parent::Field($properties);
	}

	protected function getJavascript(){
		return "
			EcomEcommerceGeocodingField.useSensor = ".Convert::raw2js($this->userSensor ? "true" : "false").";
			EcomEcommerceGeocodingField.fieldName = '".Convert::raw2js($this->getName())."';
			EcomEcommerceGeocodingField.relatedFields = ".Convert::raw2json($this->getFieldMap()).";";
	}


}
