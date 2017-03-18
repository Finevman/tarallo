<?php
namespace WEEEOpen\Tarallo\Query;


interface QueryField {
	public function allowMultipleFields();
	public function isKVP();
	public function parse($parameter);
	public function validate();
	public function isDefault();
	public function getContent();
}