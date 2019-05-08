<?php

namespace bamboo\domain\entities;

use bamboo\business\carrier\ACarrierHandler;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;

/**
 * Class CCarrier
 * @package bamboo\app\domain\entities
 */
class CCarrier extends AEntity
{
    protected $entityTable = 'Carrier';
    protected $primaryKeys = array('id');

    /**
     * @return ACarrierHandler|ACarrierHandler
     * @throws BambooException
     */
    public function getHandler() {
        if(!isset($this->handlerImpl)) {
            if(empty($this->implementation)) $this->handlerImpl = null;
            else {
                $class = $this->implementation;
                if(!class_exists($class)) throw new BambooException("Could not send handle $this->name shipment");

                /** @var ACarrierHandler $this->handlerImpl */
                $this->handlerImpl = new $class([]);
            }
        }
        return $this->handlerImpl;
    }
}