<?php

namespace WEEEOpen\Tarallo\Server\Database;

use WEEEOpen\Tarallo\Server\Feature;
use WEEEOpen\Tarallo\Server\Item;
use WEEEOpen\Tarallo\Server\ItemIncomplete;
use WEEEOpen\Tarallo\Server\ItemPrefixer;
use WEEEOpen\Tarallo\Server\ItemUpdate;
use WEEEOpen\Tarallo\Server\NotFoundException;

final class ItemDAO extends DAO {
	/**
	 * Add all items. Also starts and finishes the necessary transaction.
	 *
	 * @param array $items Items to add
	 * @param ItemIncomplete|null $parent Parent item for all these
	 *
	 * @return Item[] All inserted items, in same order, retrieved from database. Array keys are preserved.
	 * @throws \Throwable whatever was thrown inside, PHPStorm is forcind me to add a comment
	 *
	 * @see addItem to add one item (and manage transaction yourself)
	 */
	public function addItems(array $items, ItemIncomplete $parent = null) {
		if(empty($items)) {
			return [];
		}

		$heads = [];

		try {
			$this->database->beginTransaction();
			foreach($items as $k => $item) {
				$heads[$k] = $this->addItem($item, $parent);
			}
			$this->database->commit();
		} catch(\Throwable $e) {
			$this->database->rollback();
			throw $e;
		}

		return $heads;
	}

	private $addItemStatement = null;

	/**
	 * Insert a single item into the database, return its id. Basically just add a row to Item, no features are added.
	 * Must be called while in transaction.
	 *
	 * @param Item $item the item to be inserted
	 * @param ItemIncomplete $parent parent item
	 *
	 * @return Item Same item, retrieved from database
	 *
	 * @see addItems
	 */
	public function addItem(Item $item, ItemIncomplete $parent = null) {
		if($parent === null) {
			return $this->addItemInternal($item);
		} else {
			return $this->addItemInternal($item, $parent);
		}
	}

	/**
	 * @see addItem
	 *
	 * @param Item $item the item to be inserted
	 * @param ItemIncomplete $parent parent item
	 * @param bool $last leave it as it is
	 *
	 * @return null|Item Outer call always returns Item, internal ones (it's recursive) return null
	 */
	public function addItemInternal(Item $item, ItemIncomplete $parent = null, $last = true) {
		$pdo = $this->getPDO();
		if(!$pdo->inTransaction()) {
			throw new \LogicException('addItem called outside of transaction');
		}

		if(!$item->hasCode()) {
			$prefix = ItemPrefixer::get($item);
			$code = $this->getNewCode($prefix);
			$item->setCode($code);
		}

		if($this->addItemStatement === null) {
			// TODO: add brand, model, variant
			$this->addItemStatement = $pdo->prepare('INSERT INTO Item (`Code`, Movable) VALUES (:cod, :mov)');
		}

		try {
			$this->addItemStatement->bindValue(':cod', $item->getCode(), \PDO::PARAM_STR);
			// PDO::PARAM_BOOL doesn't work, query fails FOR NO REASON, cleanly returns false with no errors
			// Known bug from 2006, still alive and well in PHP 7.1 (or a regression?): https://bugs.php.net/bug.php?id=38546
			$this->addItemStatement->bindValue(':mov', (int) $item->isMovable(), \PDO::PARAM_INT);
			if(!$this->addItemStatement->execute()) {
				throw new DatabaseException('Cannot insert item ' . $item->getCode() . ' for unknown reasons');
			}
		} finally {
			$this->addItemStatement->closeCursor();
		}

		/** @var Item $item */
		$this->database->featureDAO()->addFeatures($item);
		$this->database->treeDAO()->addToTree($item, $parent);

		$childItems = $item->getContents();
		foreach($childItems as $childItem) {
			// yay recursion!
			$this->addItemInternal($childItem, $item, false);
		}

		if($last) {
			// TODO: test stuff
			//$this->database->commit();
			return $this->getItem($item, null);
		} else {
			return null;
		}
	}

	private $getNewCodeStatement = null;

	/**
	 * Get a new sequential code directly from database, for a given prefix
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	private function getNewCode($prefix) {
		if($this->getNewCodeStatement === null) {
			$this->getNewCodeStatement = $this->getPDO()->prepare('SELECT GenerateCode(?)');
		}
		try {
			$this->getNewCodeStatement->execute([$prefix]);
			$code = $this->getNewCodeStatement->fetch(\PDO::FETCH_NUM)[0];
			if($code === null) {
				throw new \LogicException("Cannot generate code for prefix $prefix, NULL returned");
			}

			return (string) $code;
		} finally {
			$this->getNewCodeStatement->closeCursor();
		}
	}

	private $getItemQuery = null;

	/**
	 * Get a single item (and its content)
	 *
	 * @param ItemIncomplete $item
	 * @param null $token
	 * @param int $depth
	 *
	 * @return Item
	 */
	public function getItem(ItemIncomplete $item, $token = null, $depth = 10) {
		if($token !== null && !$this->checkToken($item, $token)) {
			throw new NotFoundException();
		}

		if(!is_int($depth)) {
			throw new \InvalidArgumentException('Depth must be an integer, ' . gettype($token) . ' given');
		}

		if($this->getItemQuery === null) {
			$this->getItemQuery = $this->getPDO()->prepare(<<<EOQ
				SELECT `Code`, `Brand`, `Model`, `Variant`, `Movable`, Ancestor AS Parent
				FROM Tree
				JOIN Item ON Descendant=`Code` -- right join? qualcosa? Boh. È ovvio (e conseguentemente ineffabile) a tutti meno che a me come fare la query del subtree. C'era quel MAX(IF(...)) incomprensibile da talmente era OVVISSIMO E SEMPLICISSIMO, infatti. 
				WHERE Descendant IN (
					SELECT DISTINCT Descendant
					FROM Tree
					WHERE Ancestor = ?
					AND Depth < ?
					ORDER BY Depth
				)
				AND Depth = 1
EOQ
			);
		}

		$this->getItemQuery->execute([$item->getCode(), $depth]);

		if(($row = $this->getItemQuery->fetch(\PDO::FETCH_ASSOC)) === false) {
			throw new NotFoundException();
		}

		$flat = [];

		$flat[] = $head = new Item($row['Code']);

		$this->fillItem($head, $row['Brand'], $row['Model'], $row['Variant'], $row['Movable']);
		$head->addAncestors($this->database->treeDAO()->getPathTo($head));

		while(($row = $this->getItemQuery->fetch(\PDO::FETCH_ASSOC)) !== false) {
			if(!isset($flat[$row['Parent']])) {
				throw new \LogicException('Broken tree: got ' . $row['Code'] . ' before its parent ' . $row['Parent']);
			}
			$this->fillItem(new Item($row['Code']), $row['Brand'], $row['Model'], $row['Variant'], $row['Movable'],
				$flat[$row['Parent']]);
		}

		return $head;
	}

	/**
	 * Check that item can be obtained with a token.
	 *
	 * @param ItemIncomplete $item
	 * @param string $token
	 *
	 * @return bool true if possible, false if wrong token or item doesn't exist
	 */
	private function checkToken(ItemIncomplete $item, $token) {
		if(!is_string($token)) {
			throw new \InvalidArgumentException('Token must be a string, ' . gettype($token) . ' given');
		}

		$tokenquery = $this->getPDO()->prepare(<<<EOQ
			SELECT IF(COUNT(*) > 0, TRUE, FALSE)
			FROM Item
			WHERE `Code` = ? AND Token = ?
EOQ
		);

		$tokenquery->execute([$item->getCode(), $token]);
		$result = $tokenquery->fetch(\PDO::FETCH_NUM);
		if(!is_bool($result[0])) {
			throw new \LogicException('Result is not boolean');
		}
		if($result[0] === true) {
			return true;
		} else {
			return false;
		}
	}

	private function fillItem(Item $item, $brand, $model, $variant, $movable, Item $parent = null) {
		$brand === null ?: $item->addFeature(new Feature('brand', $brand));
		$model === null ?: $item->addFeature(new Feature('model', $model));
		// TODO: these shouldn't be plain features... also, don't discard $variant
		if(!$movable) {
			$item->addFeature(new Feature('soldered-in-place', 'yes'));
		}

		if($parent !== null) {
			$parent->addContent($item);
		}
	}

	private function setItemDefaults(ItemUpdate $item) {
		// TODO: reimplement
	}
}
