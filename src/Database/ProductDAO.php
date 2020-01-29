<?php


namespace WEEEOpen\Tarallo\Database;


use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\String_;
use WEEEOpen\Tarallo\ItemWithCode;
use WEEEOpen\Tarallo\ItemWithFeatures;
use WEEEOpen\Tarallo\ItemWithProduct;
use WEEEOpen\Tarallo\NotFoundException;
use WEEEOpen\Tarallo\Product;
use WEEEOpen\Tarallo\ProductCode;

final class ProductDAO extends DAO{
	public function addProduct(Product $product) {

		$statement = $this->getPDO()->prepare('INSERT INTO Product (`Brand`, `Model`, `Variant`) VALUES (:brand, :model, :variant)');
		try {
			$statement->bindValue(':brand', $product->getBrand(), \PDO::PARAM_STR);
			$statement->bindValue(':model', $product->getModel(), \PDO::PARAM_STR);
			$statement->bindValue(':variant', $product->getVariant(), \PDO::PARAM_STR);
			$result = $statement->execute();
			assert($result === true, 'Add product');
		} catch(\PDOException $e) {
			if($e->getCode() === '23000' && $statement->errorInfo()[1] === 1062) {
				throw new DuplicateItemCodeException((string) $product, 'Product already exists: ' . (string) $product);
			}
			throw $e;
		} finally {
			$statement->closeCursor();
		}

		$this->database->featureDAO()->setFeatures($product);
	}

	/**
	 * It gets product in exact match, requires model, brand and variant
	 *
	 * @param ProductCode $product
	 *
	 * @return Product
	 */
	public function getProduct(ProductCode $product): Product {
		$statement = $this->getPDO()->prepare('SELECT Brand, Model, Variant FROM Product WHERE Brand = :prod AND Model = :model AND Variant = :variant');
		try {
			$statement->bindValue(':prod', $product->getBrand(), \PDO::PARAM_STR);
			$statement->bindValue(':model', $product->getModel(), \PDO::PARAM_STR);
			$statement->bindValue(':variant', $product->getVariant(), \PDO::PARAM_STR);
			$result =  $statement->execute();
			assert($result === true, 'get product');
			if($statement->rowCount() === 0) {
				throw new NotFoundException();
			}
			$row = $statement->fetch(\PDO::FETCH_ASSOC);
			$product = new Product($row['Brand'], $row['Model'], $row['Variant']);
			$this->database->featureDAO()->addFeaturesTo($product);

			return $product;
		} finally{
			$statement->closeCursor();
		}
	}

	/**
	 *  It returns an array of product through brand and model. So it will get all variants of that product.
	 *
	 * @param String $brand
	 * @param String $model
	 *
	 * @return Product[] or empty array if none
	 */
	public function getProducts(String $brand, String $model): array {
		$statement = $this->getPDO()->prepare('SELECT  Brand, Model, Variant FROM Product WHERE Brand = :prod AND Model = :model');
		try {
			$statement->bindValue(':prod', $brand, \PDO::PARAM_STR);
			$statement->bindValue(':model', $model, \PDO::PARAM_STR);
			$result = $statement->execute();
			assert($result === true, 'get products');
			if($statement->rowCount() === 0) {
				return [];
			}
			$result = [];
			// TODO: this can be optimized, a single query can get all the features (instead of N queries in addFeaturesTo)
			while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
				$product = new Product($row['Brand'], $row['Model'], $row['Variant']);
				$this->database->featureDAO()->addFeaturesTo($product);
				$result[] = $product;
			}

			return $result;
		} finally{
			$statement->closeCursor();
		}
	}

	public function deleteProduct(ProductCode $product): bool {
		$statement = $this->getPDO()->prepare('DELETE FROM Product WHERE Brand = :prod AND Model = :mod AND Variant = :var ');
		try{
			$statement->bindValue(':prod', $product->getBrand(), \PDO::PARAM_STR);
			$statement->bindValue(':mod', $product->getModel(), \PDO::PARAM_STR);
			$statement->bindValue(':var', $product->getVariant(), \PDO::PARAM_STR);
			$result =  $statement->execute();
			assert($result === true, 'Delete product');
			return $statement->rowCount() > 0;
		} finally{
			$statement->closeCursor();
		}
	}

	/**
	 * Adds product to every item in the array (or does nothing if there's no product)
	 *
	 * @param ItemWithProduct[]|ItemWithFeatures[] $items
	 *
	 * @return ItemWithProduct[]|ItemWithFeatures[]
	 */
	public function getProductsAll(array $items): array {
		foreach($items as $item) {
			$brand = $item->getFeatureValue('brand');
			$model = $item->getFeatureValue('model');
			$variant = $item->getFeatureValue('variant');
			if(isset($brand) && isset($model) && isset($variant)) {
				try {
					$product = $this->getProduct(new ProductCode($brand, $model, $variant));
					$item->setProduct($product);
				} catch(NotFoundException $ignored) {

				}
			}
		}

		return $items;
	}
}
