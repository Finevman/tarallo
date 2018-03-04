<?php

namespace WEEEOpen\Tarallo\APIv1;

use WEEEOpen\Tarallo\Server\Feature;
use WEEEOpen\Tarallo\Server\HTTP\InvalidPayloadParameterException;
use WEEEOpen\Tarallo\Server\Item;
use WEEEOpen\Tarallo\Server\ItemFeatures;
use WEEEOpen\Tarallo\Server\ItemIncomplete;

class ItemBuilder {
	/**
	 * Build an Item, return it.
	 *
	 * @param array $input Decoded JSON from the client
	 * @param string|null $code Code for new item, if explicitly set
	 * @param ItemIncomplete|null $parent passed by reference, will contain the direct parent, if set.
	 *
	 * @return Item
	 */
	public static function ofArray(array $input, $code, &$parent) {
		$item = self::ofArrayInternal($input, $code);

		if(isset($input['parent'])) {
			try {
				$parent = new ItemIncomplete($input['parent']);
			} catch(\InvalidArgumentException $e) {
				throw new InvalidPayloadParameterException('parent', $input['parent'], 'Parent: ' . $e->getMessage());
			}
		} else {
			$parent = null;
		}

		return $item;
	}

	/**
	 * @see ofArray
	 *
	 * @param array $input Decoded JSON from the client
	 * @param string|null $code Code for new item, if explicitly set
	 * @param boolean $inner Used for recursion
	 *
	 * @TODO: handle brand, model, variant
	 *
	 * @return Item
	 */
	private static function ofArrayInternal(array $input, $code, $inner = false) {
		try {
			$item = new Item($code);
		} catch(\InvalidArgumentException $e) {
			throw new InvalidPayloadParameterException('*', $code, $e->getMessage());
		}

		if($inner && isset($input['parent'])) {
			throw new InvalidPayloadParameterException('parent', $input['parent'],
				'Cannot set parent for internal items');
		}

		if(isset($input['features'])) {
			try {
				self::addFeatures($input['features'], $item);
			} /** @noinspection PhpUndefinedClassInspection */ catch(\TypeError $e) {
				throw new InvalidPayloadParameterException('features', '',
					'Features must be an array, ' . gettype($input['features']) . ' given');
			}
		}

		if(isset($input['contents'])) {
			if(!is_array($input['contents'])) {
				throw new InvalidPayloadParameterException('contents', '',
					'Contents must be an array, ' . gettype($input['contents']) . ' given');
			}
			foreach($input['contents'] as $other) {
				$item->addContent(self::ofArrayInternal($other, true));
			}
		}

		return $item;
	}

	/**
	 * Process features to be added
	 *
	 * @param string[] $features The usual key-value pair for features
	 * @param ItemFeatures $item Where to add those features
	 *
	 * @see addFeaturesDelta
	 */
	public static function addFeatures(array $features, ItemFeatures $item) {
		foreach($features as $name => $value) {
			try {
				$item->addFeature(new Feature($name, $value));
			} catch(\Throwable $e) {
				throw new InvalidPayloadParameterException(is_string($name) ? $name : '?', $value,
					'Features: ' . $e->getMessage());
			}
		}
	}

	/**
	 * Processes feature to be added AND removed
	 *
	 * @param string[] $features The usual key-value pair for features
	 * @param ItemFeatures $item Where to add those features
	 *
	 * @return string[] Features to be removed
	 *
	 * @see addFeatures
	 */
	public static function addFeaturesDelta(array $features, ItemFeatures $item) {
		$delete = [];
		$add = [];

		foreach($features as $name => $value) {
			if($value === null) {
				$delete[] = $name;
			} else {
				$add[$name] = $value;
			}
		}

		return $delete;
	}
}
