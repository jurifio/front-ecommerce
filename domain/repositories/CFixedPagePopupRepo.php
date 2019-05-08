<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CFixedPagePopup;


/**
 * Class CArticleRepo
 * @package bamboo\app\domain\repositories
 */
class CFixedPagePopupRepo extends ARepo
{

    /**
     * @param string $title
     * @param string $subtitle
     * @param string $text
     * @param string $couponEventId
     * @param int $isActive
     * @param int $fixedPageId
     * @param string $titleSub
     * @param string $subtitleSub
     * @param string $textSub
     * @return CFixedPagePopup
     */
    public function insertNewPopup(string $title, string $subtitle, string $text, string $couponEventId, int $isActive, int $fixedPageId, string $titleSub, string $subtitleSub, string $textSub) : CFixedPagePopup
    {
        /** @var CFixedPagePopup $fixedPagePopup */
        $fixedPagePopup = $this->getEmptyEntity();
        $fixedPagePopup->title = $title;
        $fixedPagePopup->subtitle = $subtitle ?: null;
        $fixedPagePopup->text = $text ?: null;
        $fixedPagePopup->couponEventId = $couponEventId ?: null;
        $fixedPagePopup->isActive = $isActive;
        $fixedPagePopup->fixedPageId = $fixedPageId;
        $fixedPagePopup->titleSubscribed = $titleSub;
        $fixedPagePopup->subtitleSubscribed = $subtitleSub;
        $fixedPagePopup->textSubscribed = $textSub;
        $fixedPagePopup->smartInsert();

        return $fixedPagePopup;
    }

    /**
     * @param int $popupId
     * @param string $title
     * @param string $subtitle
     * @param string $text
     * @param string $couponEventId
     * @param string $titleSub
     * @param string $subtitleSub
     * @param string $textSub
     * @return CFixedPagePopup
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function updateFixedPagePopup(int $popupId, string $title, string $subtitle, string $text, string $couponEventId, string $titleSub, string $subtitleSub, string $textSub) : CFixedPagePopup
    {
        /** @var CFixedPagePopup $fPP */
        $fPP = $this->findOneBy(['id'=>$popupId]);
        $fPP->title = $title;
        $fPP->subtitle = $subtitle ?: null;
        $fPP->text = $text ?: null;
        $fPP->couponEventId = $couponEventId ?: null;
        $fPP->titleSubscribed = $titleSub ?: null;
        $fPP->subtitleSubscribed = $subtitleSub ?: null;
        $fPP->textSubscribed = $textSub ?: null;
        $fPP->update();

        return $fPP;
    }

    /**
     * @param int $popupId
     * @return bool
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function deactivateFixedPagePopup(int $popupId) : bool
    {
        /** @var CFixedPagePopup $fPP */
        $fPP = $this->findOneBy(['id'=>$popupId]);
        $fPP->isActive = 0;
        $fPP->update();

        return true;
    }

    public function fetchEntityByPopup()
    {
        $fixedPageId = \Monkey::app()->router->getMatchedRoute()->getComputedFilter('id');
        return \Monkey::app()->repoFactory->create('FixedPagePopup')->findOneBy(['fixedPageId'=>$fixedPageId, 'isActive'=>1]);
    }

}