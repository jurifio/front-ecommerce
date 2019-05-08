<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CProduct;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProductName;

class CProductNameTranslationRepo extends ARepo
{
    public function findByName($name, $throw = false) {
        $nameManaged = $this->manageExclamationMark($name);
        $pnRepo = \Monkey::app()->repoFactory->create('ProductName');
        $pn = $pnRepo->findBy(['name' => $nameManaged['without']]);
        $pnEx = $pnRepo->findBy(['name' => $nameManaged['withExclamation']]);

        if (($pn->count()) || ($pnEx->count())) {
            $pn = ($pn->count()) ? $pn : $pnEx;
            return $pn;
        }

        if ($throw) throw new BambooException('Il nome prodotto non esiste. Deve essere prima creato.');
        return false;
    }

    public function findTranslation($name, $lang) {
        return $this->findOneBy(['name' => $name, 'landIg' => $lang]);
    }

    public function findAllTranslation($name, $lang) {
        return $this->findBy(['name' => $name]);
    }

    public function insertName($name) {
        $isName = $this->findByName($name);
        if (!$isName) {
            return $this->bareInsertName($name);
        }
        return $isName;
    }

    private function bareInsertName($name) {
        $pn = \Monkey::app()->repoFactory->create('ProductName')->getEmptyEntity();
        $nameManaged = $this->manageExclamationMark($name);
        $pn->name = $nameManaged['withExclamation'];
        $pn->langId = 1;
        $pn->translation = $nameManaged['without'];
        $pn->insert();
        return $pn;
    }

    public function insertTranslation($name, $langId, $translation) {
        $pnRepo = \Monkey::app()->repoFactory->create('ProductName');
        $pntRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');

        $nameManaged = $this->manageExclamationMark($name);
        $pn = $pnRepo->findOneBy(['name' => $nameManaged['without'], 'langId' => 1]);
        $pnEx = $pnRepo->findOneBy(['name' => $nameManaged['withExclamation'], 'langId' => 1]);

        $pnt = $pntRepo->findOneBy(['name' => $nameManaged['without'], 'langId' => 1]);
        $pntEx = $pntRepo->findOneBy(['name' => $nameManaged['withExclamation'], 'langId' => 1]);

        if ((!$pn) && (!$pnt) && (!$pnEx) && (!$pntEx)) {
            throw new \Exception('Non posso inserire la traduzione di un nome inesistente');
        }

        if ((!$pn) && ($pnt)) {
            $pn = $pnRepo->getEmptyEntity();
            $pn->name = $pnt->name;
            $pn->langId = 1;
            $pn->translation = $pnt->name;
            $pn->insert();
        }

        if ((!$pnEx) && ($pntEx)) {
            $pnEx = $pnRepo->getEmptyEntity();
            $pnEx->name = $pntEx->name;
            $pnEx->langId = 1;
            $pnEx->translation = $pntEx->name;
            $pnEx->insert();
        }

        $pn = $pnRepo->findOneBy(['name' => $nameManaged['without'], 'langId' => $langId]);
        $pnEx = $pnRepo->findOneBy(['name' => $nameManaged['withExclamation'], 'langId' => $langId]);

        $pnt = $pntRepo->findBy(['name' => $nameManaged['without'], 'langId' => $langId]);
        $pntEx = $pntRepo->findBy(['name' => $nameManaged['withExclamation'], 'langId' => $langId]);

        if ((!$pn) && (!$pnEx)) {
            $this->bareInsertTranslation($name, $langId, $translation, $pnRepo);
        } elseif (($pn) XOR ($pnEx)) {
            $entity = ($pn) ? $pn : $pnEx;
            $this->bareUpdateTranslation($translation, $entity);
        } elseif (($pn) && ($pnEx)) {
            throw new \Exception('Non possono esserci nuovi nomi che corrisondono a nomi già normalizzati');
        }

        foreach($pnt as $n) {
            $this->bareUpdateTranslation($translation, $n);
        }
        foreach($pntEx as $n) {
            $this->bareUpdateTranslation($translation, $n);
        }
    }

    private function bareInsertTranslation($name, $langId, $translation) {
        /** @var $em AEntity */
        $repo = \Monkey::app()->repoFactory->create('ProductName');
        $em = $repo->getEmptyEntity();
        $em->name = $name;
        $em->langId = $langId;
        $em->translation = $translation;
        return $em->insert();
    }

    private function bareUpdateTranslation($translation, $entity) {
        $entity->translation = $translation;
        return $entity->update();
    }

    private function manageExclamationMark($name) {
        $ret = [];
        if (' !' == substr($name, strlen($name) - 2, 2)) {
            $ret['withExclamation'] = $name;
            $ret['without'] = substr($name, 0, strlen($name) - 2);
        } else {
            $ret['withExclamation'] = $name . ' !';
            $ret['without'] = $name;
        }
        return $ret;
    }

    public function saveProductName($productId, $productVariantId, $name) {
        $pnRepo = \Monkey::app()->repoFactory->create('ProductName');
        $pntRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');

        $pn = $pnRepo->findBy(['name' => $name]);
        if (!$pn) throw new BambooException('Non può essere salvato un prodotto usando un nome non presente nel database');
        foreach($pn as $v) {
            $pnt = $pntRepo->getEmptyEntity();
            $pnt->productVariantId = $productVariantId;
            $pnt->productId = $productId;
            $pnt->name = $v->translation;
            $pnt->langId = $v->langId;
            $pnt->insert();
        }
    }

    /**
     * @param $productId
     * @param $productVariantId
     * @param $name
     * @param bool $insertIfNotExists
     * @throws BambooException
     */
    public function saveNameForNewProduct($productId, $productVariantId, $name, $insertNameIfNotExists = false) {
        if($name instanceof CProductName) {
            $name = $name->name;
        } else {
            $name = trim($name);
        }
        $pn = $this->findByName($name);
        if (!$pn) {
            if ($insertNameIfNotExists) {
                $pn = $this->bareInsertName($name);
            } else {
                throw new BambooException('Non può essere salvato un prodotto usando un nome non presente nel database');
            }
        }
        try {
            foreach ($pn as $v) {
                $pnt = $this->getEmptyEntity();
                $pnt->productVariantId = $productVariantId;
                $pnt->productId = $productId;
                $pnt->name = $v->translation;
                $pnt->langId = $v->langId;
                $pnt->insert();
            }
        } catch(BambooException $e) {
            throw $e;
        }
    }

    public function updateProductName($productId, $productVariantId, $name) {

        $pn = $this->findByName($name);
        if (!$pn) throw new BambooException('Non può essere salvato un prodotto usando un nome non presente nel database');

        $pnt = $this->findBy(['productId' => $productId, 'productVariantId' => $productVariantId]);
        foreach($pnt as $v) {
            $v->delete();
        }
        foreach($pn as $v) {
            $pnt = $this->getEmptyEntity();
            $pnt->productId = $productId;
            $pnt->productVariantId = $productVariantId;
            $pnt->langId = $v->langId;
            $pnt->name = $v->translation;
            $pnt->insert();
        }
        return true;
    }

    public function countProductsWithName($name) {
        return \Monkey::app()->dbAdapter->query(
            "SELECT count(*) as count FROM ((ProductNameTranslation as `pn` JOIN Product as `p` ON `p`.`productVariantId` = `pn`.`productVariantId`) JOIN `ProductStatus` as `ps` ON `p`.`productStatusId` = `ps`.`id`) WHERE `langId` = 1 AND `pn`.`name` = ? AND `ps`.`code` in ('P', 'A')",
            [$name])->fetch()['count'];
    }

    public function updateTranslationFromName($newName, $oldName) {
        $codes = [];
        $pntIt = $this->findBy(['name' => $oldName, 'langId' => 1]);
        $pnOC = \Monkey::app()->repoFactory->create('ProductName')->findBy(['name' => $newName]);
        $count = 0;
        foreach($pntIt as $v) {
            $codes[$count] = [];
            $codes[$count]['productVariantId'] = $v->productVariantId;
            $codes[$count]['productId'] = $v->productId;
            $count++;
        }
        foreach($codes as $v) {
            $pntOC = $this->findBy(['productVariantId' => $v['productVariantId']]);
            foreach($pntOC as $pnt) {
                $pnt->delete();
            }
        }
        foreach($codes as $v) {
            foreach($pnOC as $pne) {
                $pnt = $this->getEmptyEntity();
                $pnt->productId = $v['productId'];
                $pnt->productVariantId = $v['productVariantId'];
                $pnt->langId = $pne->langId;
                $pnt->name = $pne->translation;
                $pnt->insert();
            }
        }
    }

    public function deleteDeadNames() {
        $pntRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');
        $res = \Monkey::app()->dbAdapter->query('SELECT `translation` FROM `ProductName`  WHERE langId = 1', [])->fetchAll();
        $countDeleted = 0;
        foreach($res as $k => $v) {
            $pnt = $pntRepo->findOneBy(['name' => $v['translation']]);
            if (!$pnt) {
                $pnC = $pntRepo->findByName($v['translation']);
                if (!is_object($pnC)) return 0;
                foreach($pnC as $n) {
                    $n->delete();
                    $countDeleted++;
                }
            }
        }
        return $countDeleted;
    }
}