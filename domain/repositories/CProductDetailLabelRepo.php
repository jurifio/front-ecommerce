<?php


namespace bamboo\domain\repositories;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\entities\CProductDetailLabel;
use bamboo\domain\entities\CProductDetailLabelTranslation;

/**
 * Class CProductDetailLabelRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 11/05/2018
 * @since 1.0
 */
class CProductDetailLabelRepo extends ARepo
{


    /**
     * @param int $lang
     * @param $names
     * @return array
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function insertDetailLabel(int $lang, $names) : array {

        $ids = [];

        $slugify = new CSlugify();
        foreach ($names as $name){

            $max = \Monkey::app()->dbAdapter->query('SELECT MAX(ProductDetailLabel.id) as max
                                                        FROM ProductDetailLabel', [])->fetch();

            /** @var CProductDetailLabel $newDetailLabel */
            $newDetailLabel = $this->getEmptyEntity();
            $newDetailLabel->id = $max['max'] + 3;
            $newDetailLabel->slug = $slugify->slugify($name['name']);
            $newDetailLabel->order = $name['pr'];
            $newDetailLabel->smartInsert();

            /** @var CRepo $ndltRepo */
            $ndltRepo = \Monkey::app()->repoFactory->create('ProductDetailLabelTranslation');

            /** @var CProductDetailLabelTranslation $newNdlt */
            $newNdlt = $ndltRepo->getEmptyEntity();
            $newNdlt->productDetailLabelId = $newDetailLabel->id;
            $newNdlt->langId = $lang;
            $newNdlt->name = $name['name'];
            $newNdlt->smartInsert();

            $ids[] = $newDetailLabel->id;
        }

        return $ids;
    }

}