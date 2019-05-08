<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CProductSize;
use bamboo\domain\entities\CProductSizeGroup;
use bamboo\domain\entities\CProductSizeGroupHasProductSize;
use bamboo\domain\entities\CProductSizeMacroGroup;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CProductSizeGroupRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CProductSizeGroupRepo extends ARepo
{

    /**
     * @param CProductSizeMacroGroup $productSizeMacroGroup
     * @param $rowNum
     * @param $shift
     * @return array|bool
     */
    public function deleteGroupPosition(CProductSizeMacroGroup $productSizeMacroGroup, $rowNum, $shift)
    {
        $productSizeGroups = $productSizeMacroGroup->productSizeGroup;

        $products = [];
        foreach ($productSizeGroups as $productSizeGroup) {
            /** @var CProductSizeGroup $productSizeGroup */
            /** @var CProductSizeGroupHasProductSize $productSizeGroupHasProductSize */
            $productSizeGroupHasProductSize = $productSizeGroup->productSizeGroupHasProductSize->findOneByKey('position', $rowNum);

            if ($productSizeGroupHasProductSize) {
                if (!$productSizeGroupHasProductSize->isProductSizeCorrespondenceDeletable()) {
                    $products += $productSizeGroupHasProductSize->getProductCorrespondences();
                } else if (count($products) == 0) {
                    $productSizeGroupHasProductSize->delete();
                }
            }
        }

        if (count($products) != 0) {
            \Monkey::app()->repoFactory->rollback();

            return [
                'message' => 'Non ho potuto eliminare la riga perchè ci sono Prodotti collegato',
                'products' => $products
            ];
        }

        if ($shift == 'up') {
            $fromRow = $rowNum + 1;
            $toRow = $rowNum;
        } elseif ($shift == 'down') {
            $fromRow = $rowNum - 1;
            $toRow = $rowNum;
        } else {
            \Monkey::app()->repoFactory->commit();
            return true;
        }

        return $this->moveSizesPosition($productSizeMacroGroup, $fromRow, $toRow,false);

    }

    /**
     * @param CProductSizeMacroGroup $productSizeMacroGroup
     * @param $fromRow
     * @param $toRow
     * @param bool $insert
     * @return bool
     * @throws BambooException
     */
    public function moveSizesPosition(CProductSizeMacroGroup $productSizeMacroGroup, $fromRow, $toRow,$insert = true)
    {
        $checkSql = "SELECT * 
                FROM ProductSizeGroup psg JOIN 
                  ProductSizeGroupHasProductSize psghps ON psg.id = psghps.productSizeGroupId
                WHERE psg.productSizeMacroGroupId = ? AND psghps.position = ?";

        $modifier = $toRow - $fromRow;
        if ($fromRow < $toRow) {
            $versusName = 'basso';
            $updateVersus = 'DESC';
            if($insert) {
                $maiorMinor = '>';
                $toRow = 0;
            } else {
                $maiorMinor = '<=';
            }
        } elseif ($fromRow > $toRow) {
            $versusName = 'alto';
            $updateVersus = 'ASC';
            if($insert) {
                $maiorMinor = '<';
                $toRow = 35;
            } else {
                $maiorMinor = '>=';
            }
        } elseif ($fromRow == $toRow) {
            throw new BambooException('Verso non valido');
        }

        $res = \Monkey::app()->dbAdapter->query($checkSql, [
            $productSizeMacroGroup->id,
            $toRow
        ])->fetchAll();
        if (count($res)) throw new BambooException('Non posso scorrere in %s se la riga %d non è vuota', [$versusName, $toRow]);

        $bind = [
            $modifier,
            $fromRow
        ];

        $groups = \Monkey::app()->dbAdapter->query(
            'SELECT id FROM ProductSizeGroup WHERE productSizeMacroGroupId = ?', [$productSizeMacroGroup->id])->fetchAll(\PDO::FETCH_COLUMN);
        $questionMarks = [];
        foreach ($groups as $group) {
            $questionMarks[] = '?';
            $bind[] = $group;
        }

        $moveSql = "UPDATE ProductSizeGroupHasProductSize psghps 
                    SET psghps.position = psghps.position + ?
                    WHERE psghps.position " . $maiorMinor . " ? AND psghps.productSizeGroupId IN (" . implode(',', $questionMarks) . ")
                    ORDER BY position ";

        return (bool)\Monkey::app()->dbAdapter->query($moveSql . $updateVersus, $bind)->countAffectedRows() > 0;
    }
}
