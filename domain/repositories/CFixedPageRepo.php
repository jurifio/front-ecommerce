<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CFixedPage;


/**
 * Class CArticleRepo
 * @package bamboo\app\domain\repositories
 */
class CFixedPageRepo extends ARepo
{
    public function fetchEntityByPageId($params,$args)
    {
        $id = $this->app->router->getMatchedRoute()->getComputedFilter('id');
        return $this->em()->findOneBy(["id"=>$id,"langId"=>$this->app->getLang()->getId()]);
    }

    /**
     * @param int $id
     * @param int $fixedPageTypeId
     * @param int $langId
     * @param $title
     * @param $subtitle
     * @param string $slug
     * @param string $text
     * @param string $titleTag
     * @param string $metaDescription
     * @return CFixedPage
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function updateFixedPage(int $id, int $fixedPageTypeId, int $langId, $title, $subtitle, string $slug, string $text, string $titleTag, string $metaDescription): CFixedPage
    {

        /** @var CFixedPage $fixedPage */
        $fixedPage = $this->findOneBy(['id'=>$id, 'langId' => $langId, 'fixedPageTypeId'=>$fixedPageTypeId]);
        $fixedPage->title = $title;
        $fixedPage->subtitle = $subtitle;
        $fixedPage->slug = $slug;
        $fixedPage->fixedPageTypeId = $fixedPageTypeId;
        $fixedPage->text = $text;
        $fixedPage->titleTag = $titleTag;
        $fixedPage->metaDescription = $metaDescription;
        $fixedPage->update();

        return $fixedPage;
    }

    /**
     * @param int $fixedPageTypeId
     * @param int $langId
     * @param string $title
     * @param string $subtitle
     * @param string $slug
     * @param string $text
     * @param string $titleTag
     * @param string $metaDescription
     * @return CFixedPage
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function insertFixedPage(int $fixedPageTypeId, int $langId, $title, $subtitle, string $slug, string $text, string $titleTag, string $metaDescription): CFixedPage
    {

        /** @var CFixedPage $fixedPage */
        $fixedPage = $this->getEmptyEntity();
        $fixedPage->id = $this->getNextId();
        $fixedPage->fixedPageTypeId = $fixedPageTypeId;
        $fixedPage->langId = $langId;
        $fixedPage->title = $title;
        $fixedPage->subtitle = $subtitle;
        $fixedPage->slug = $slug;
        $fixedPage->text = $text;
        $fixedPage->titleTag = $titleTag;
        $fixedPage->metaDescription = $metaDescription;
        $fixedPage->smartInsert();

        return $fixedPage;
    }

    /**
     * @return int
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function getNextId(): int {
        return \Monkey::app()->dbAdapter->query('SELECT max(`id`) as max FROM `FixedPage`', [])->fetchAll()[0]['max'] + 1;
    }
}