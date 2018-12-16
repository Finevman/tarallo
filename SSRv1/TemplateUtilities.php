<?php

namespace WEEEOpen\Tarallo\SSRv1;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use WEEEOpen\Tarallo\Server\Feature;

class TemplateUtilities implements ExtensionInterface {
	public $template;

	public function __construct() {

	}

	public function register(Engine $engine) {
		$engine->registerFunction('u', 'rawurlencode');
		$engine->registerFunction('getPrintableFeatures', [$this, 'getPrintableFeatures']);
		$engine->registerFunction('printFeature', [$this, 'printFeature']);
		$engine->registerFunction('contentEditableWrap', [$this, 'contentEditableWrap']);
		$engine->registerFunction('getOptions', [$this, 'getOptions']);
		$engine->registerFunction('asTextContent', [$this, 'asTextContent']);
	}

	/**
	 * @param Feature[] $features
	 *
	 * @return string[][] Translated group name => [UltraFeature, UltraFeature, ...]
	 */
	public function getPrintableFeatures(array $features) {
		$groups = [];
		$temp = [];

		foreach($features as $feature) {
			/** @noinspection PhpUndefinedMethodInspection It's there. */
			$ultra = new UltraFeature($feature, $this->template->data()['lang'] ?? 'en');
			$temp[Feature::getGroup($feature->name)][] = $ultra;
		}
		unset($features);

		ksort($temp);
		foreach($temp as $group => &$features) {
			usort($features, [TemplateUtilities::class, 'featureNameSort']);
			$groups[FeaturePrinter::printableGroup($group)] = $features;
		}

		return $groups;
	}

	private static function featureNameSort(UltraFeature $a, UltraFeature $b) {
		return $a->name <=> $b->name;
	}

	/**
	 * Print a single feature, if you have its parts (useful for statistics).
	 * Use UltraFeature::printableValue directly if you have the entire feature.
	 *
	 * @see UltraFeature::printableValue
	 * @param string $feature Feature name
	 * @param int|double|string $value Feature value
	 * @param string|null $lang Page language code
	 * @return string nice printable value
	 */
	public function printFeature(string $feature, $value, ?string $lang): string {
		return UltraFeature::printableValue(new Feature($feature, $value), $lang ?? 'en');
	}

	/**
	 * Wrap text into paragraphs ("div" tags), to be used in contenteditable elements
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public function contentEditableWrap(string $html): string {
		$paragraphed = '<div>' . str_replace(["\r\n", "\r", "\n"], '</div><div>', $html) . '</div>';
		// According to the HTML spec, <div></div> should be ignored by browser.
		// Firefox used to insert <p><br></p> for empty lines, for <div>s it does absolutely nothing but still displays them, soooo...
		return str_replace('<div></div>', '<div><br></div>', $paragraphed);
		// Or replace with '' to remove empty lines: cool, huh?
		//return $paragraphed;
	}

	/**
	 * Get all options for an enum feature
	 *
	 * @param Feature $feature
	 *
	 * @return string[] Internal feature name => translated feature name
	 */
	public function getOptions(Feature $feature) {
		$options = Feature::getOptions($feature);
		foreach($options as $value => &$translated) {
			$translated = FeaturePrinter::printableEnumValue($feature->name, $value);
		}
		asort($options);
		return $options;
	}

	/**
	 * Convert a string into the representation that textContent would give. That is, remove newlines.
	 *
	 * @param string $something
	 *
	 * @return string
	 */
	public function asTextContent(string $something): string {
		return str_replace(["\r\n", "\r", "\n"], '', $something);
	}
}
