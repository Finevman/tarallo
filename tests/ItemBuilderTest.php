<?php

use PHPUnit\Framework\TestCase;
use WEEEOpen\Tarallo\Server\ItemIncomplete;
use WEEEOpen\Tarallo\Server\v1\InvalidPayloadParameterException;
use WEEEOpen\Tarallo\Server\v1\ItemBuilder;

class ItemBuilderTest extends TestCase {

	/**
	 * @covers ItemBuilder
	 */
	public function testInvalidCode() {
		$this->expectException(InvalidPayloadParameterException::class);
		ItemBuilder::ofArray([], 'Foo::bar? & & &', $discarded);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testValidCode() {
		$item = ItemBuilder::ofArray([], 'PC42', $discarded);
		$this->assertInstanceOf(\WEEEOpen\Tarallo\Server\Item::class, $item);
		$this->assertEquals('PC42', $item->getCode());
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testInvalidFeaturesType() {
		$this->expectException(InvalidPayloadParameterException::class);
		ItemBuilder::ofArray(['features' => 'foo'], 'PC42', $discarded);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testInvalidFeaturesName() {
		$this->expectException(InvalidPayloadParameterException::class);
		ItemBuilder::ofArray(['features' => ['invalid' => 'stuff']], 'PC42', $discarded);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testInvalidFeaturesValue() {
		$this->expectException(InvalidPayloadParameterException::class);
		ItemBuilder::ofArray(['features' => ['motherboard-form-factor' => 'not a valid form factor']], 'PC42', $discarded);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testValidFeatures() {
		$item = ItemBuilder::ofArray(['features' => ['motherboard-form-factor' => 'atx']], 'PC42', $discarded);
		$this->assertInstanceOf(\WEEEOpen\Tarallo\Server\Item::class, $item);
		$this->assertArrayHasKey('motherboard-form-factor', $item->getFeatures());
		$this->assertEquals('atx', $item->getFeatures()['motherboard-form-factor']->value);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testInvalidParent() {
		$this->expectException(InvalidPayloadParameterException::class);
		ItemBuilder::ofArray(['parent' => 'Foo::bar? & & &'], 'PC42', $discarded);
	}

	/**
	 * @covers ItemBuilder
	 */
	public function testValidParent() {
		ItemBuilder::ofArray(['parent' => 'ZonaBlu'], 'PC42', $parent);
		$this->assertInstanceOf(ItemIncomplete::class, $parent);
		$this->assertEquals('ZonaBlu', $parent);
	}
}
