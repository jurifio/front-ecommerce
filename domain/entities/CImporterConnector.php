<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\RedPandaOutOfBoundException;

/**
 * Class CImporterConnector
 * @package bamboo\app\domain\entities
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CImporterConnector extends AEntity
{
    protected $entityTable = 'ImporterConnector';
    protected $primaryKeys = ['id'];

	/**
	 * Interroga tutte le configurazioni in ordine di priorità
	 * @param $product
	 * @param $dirtyProduct
	 * @return bool
	 * @throws RedPandaOutOfBoundException
     * @readme BROKEN BROKEN BROKEN BROKEN
     * todo fare un interfaccia per questa cosa che è ultra provvisioria
     * fixme restituisce un id secco impostato da gianluca per far andare tutti i prodotti in errore;
	 */
	public function findConnectionForProduct($product,$dirtyProduct)
	{
	    return 338;
		$this->product = $product;
		$this->dirtyProduct = $dirtyProduct;
		$this->importerConnectorStart->reorder('priority');
		foreach($this->importerConnectorStart as $connectorStart)
		{
			if($this->operate($connectorStart->importerConnectorOperation)) {
				unset($this->product);
				unset($this->dirtyProduct);
				return $connectorStart->value;
			}
		}
		unset($this->product);
		unset($this->dirtyProduct);
		return false;
	}

	/**
	 * Effettua tutte le operazioni recursivamente in cascata
	 * @param $operation
	 * @return bool
	 * @throws RedPandaOutOfBoundException
	 */
	private function operate($operation)
	{
		$field = $this->retriveField($operation);

		switch($operation->importerOperator->name) {
			case "==":
				if(is_string($operation->value) && !is_numeric($operation->value)){
					$resoult = (bool) strcasecmp($field, $operation->value) == 0;
				} else {
					$resoult = $field == $operation->value;
				}
				break;
			case "!=":
				if(is_string($operation->value) && !is_numeric($operation->value)){
					$resoult = (bool) strcasecmp($field, $operation->value) != 0;
				} else {
					$resoult = $field == $operation->value;
				}
				break;
			case ">":
				$resoult = $field > $operation->value;
				break;
			case "<":
				$resoult = $field < $operation->value;
				break;
			case ">=":
				$resoult = $field >= $operation->value;
				break;
			case "<=":
				$resoult = $field <= $operation->value;
				break;
			case "in":
				$group = explode(',',$operation->value);
				$resoult = $field == $operation->value;
				break;
			case "not in":
				$resoult = $field == $operation->value;
				break;
			default:
				throw new RedPandaOutOfBoundException("Operation not yet implemented: %s", [$operation->importerOperator->operator]);
		}
		return $this->concatenate($resoult, $operation);
	}

	/**
	 * @param $resoult
	 * @param $operation
	 * @return bool
	 * @throws RedPandaOutOfBoundException
	 */
	private function concatenate($resoult, $operation)
	{
		if(is_null($operation->nextOperation)) return $resoult;
		else
			switch(strtolower($operation->importerLogicConnector->name)){
				case "and":
					return $resoult && $this->operate($operation->nextOperation);
					break;
				case "or":
					return $resoult || $this->operate($operation->nextOperation);
					break;
				case "xor":
					return $resoult xor $this->operate($operation->nextOperation);
				default:
					throw new RedPandaOutOfBoundException("Logic Connector not yet implemented: %s", [$operation->importerLogicConnector->name]);
			}
	}

	/**
	 * @param AEntity $operation
	 * @return mixed
	 * @throws RedPandaOutOfBoundException
	 */
	private function retriveField(AEntity $operation)
	{
		switch($operation->importerField->location){
			case "DirtyProduct":
				$location = $this->dirtyProduct;
				break;
			case "Product":
				$location = $this->product;
				break;
			case "Extend":
				$location = $this->dirtyProduct->extend;
				break;
			default:
				throw new RedPandaOutOfBoundException("Field location not yet implemented: %s",[$operation->importerField->location]);
		}

		switch($operation->importerField->name){
			case "audience":
				$field = "audience";
				break;
			case "cat1":
				$field = "cat1";
				break;
			case "cat2":
				$field = "cat2";
				break;
			case "sizeGroup":
				$field = "sizeGroup";
				break;
			default:
				throw new RedPandaOutOfBoundException("Field name not yet implemented: %s",[$operation->importerField->name]);
		}

		return $this->calculateFieldModifier($location,$field,$operation->importerFieldModifier);
	}

	/**
	 * @param $data
	 * @param $field
	 * @param $modifier
	 * @return mixed
	 * @throws RedPandaOutOfBoundException
	 */
	private function calculateFieldModifier($data,$field, $modifier)
	{
		try{
			if(is_null($modifier)) return $data->{$field};
			switch($modifier->modifier){
				case 'min':
					if($data instanceof \Iterator) {
						$min = null;
						foreach($data as $dat){
							if(is_null($min) || $dat->{$field} < $min) $min = $dat->{$field};
						}
						return $min;
					}
					break;
				case 'max':
					if($data instanceof \Iterator) {
						$max = null;
						foreach($data as $dat){
							if(is_null($max) || $dat->{$field} > $max) $max = $dat->{$field};
						}
						return $max;
					}
					break;
				case 'avg':
					if($data instanceof \Iterator) {
						$sum = 0;
						$i = 0;
						foreach($data as $dat){
							$i++;
							$sum+=$dat->{$field};
						}
						if($i>0) return $sum/$i;
						else return null;
					}
					break;
				case 'first':
					foreach($data as $dat){
						return $dat->{$field};
					}
					break;
				case 'last':
					$datl = null;;
					foreach($data as $dat){
						$datl = $dat;
					}
					return $datl->{$field};
					break;
				default:
					if($data instanceof \Iterator) {
						foreach($data as $dat){
							return $dat->{$field};
						}
					}
			}
			return $data->{$field};
		} catch(\Throwable $e){
			throw new RedPandaOutOfBoundException('Failed to applicate field modifier');
		}
	}
}