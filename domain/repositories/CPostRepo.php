<?php

namespace bamboo\domain\repositories;

use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class CPostRepo
 * @package bamboo\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 18/04/2016
 * @since 1.0
 */
class CPostRepo extends ARepo
{
    public function fetchEntityById()
    {
        $postId= $this->app->router->getMatchedRoute()->getComputedFilter('postId');
        return $this->em()->findOneBy(['id'=>$postId]);
    }

    public function listByLatest($limit,$orderBy,$params,$args)
    {

        $get = $this->app->router->getMatchedRoute()->getComputedFilters(true);

        //rimuovo i parametri superflui
        unset($get['loc']);
        if (array_key_exists('tag', $get)) unset($get['tag']);
        if (array_key_exists('category', $get)) unset($get['category']);
        if (array_key_exists('author', $get)) unset($get['author']);
	    //todo cambiare la query con questa generica
		$sql = "SELECT p.id, postTagId FROM Post p  LEFT OUTER JOIN
					  PostHasPostTag pt on p.id = pt.postId and p.blogId = pt.postBlogId LEFT OUTER JOIN
					  PostHasPostCategory pc on p.id = pc.postId and p.blogId = pc.postBlogId
				where p.publishDate < now() and
				      if(? is null, 1=1, pt.postTagId = ?) and
				      if(? is null, 1=1, pc.postCategoryId = ?) and
				      ifnull(?,author) = author";
        $param = ["postStatusId" => 2, "blogId" => 1];
        if (count($get)) {
            $bind = [];
            foreach ($get as $k => $v) {
                if ($k == "postTagId") $em = \Monkey::app()->repoFactory->create('PostHasPostTag')->em();
                else if ($k == "postCategoryId") $em = \Monkey::app()->repoFactory->create('PostHasPostCategory')->em();
                $postObj = $em->findBy([$k => $v], ' LIMIT ' . $limit[0] . ',' . $limit[1], '');
                foreach ($postObj as $pLoop) {
                    $bind[] = $pLoop->postId;
                }
            }
            $bind = array_unique($bind);

            $param['id'] = $bind;
        }
        return $this->em()->findBy($param,' LIMIT '.$limit[0].','.$limit[1],' ORDER BY publishDate DESC');
    }

    public function getAllPost(){

    }

	/**
	 * @param $postId
	 * @param $blogId
	 * @param array $categoryIds
	 */
    public function setCategories($postId,$blogId,array $categoryIds)
    {
        $this->app->dbAdapter->delete('PostHasPostCategory',['postId'=>$postId,'postBlogId'=>$blogId]);
        foreach ($categoryIds as $catId) {
	        if($catId == null || $catId == 'null' ) continue;
            $this->app->dbAdapter->insert('PostHasPostCategory',['postId'=>$postId,'postBlogId'=>$blogId,'postCategoryId'=>$catId]);
        }
    }

	/**
	 * @param $postId
	 * @param $blogId
	 * @param array $tagIds
	 */
    public function setTags($postId,$blogId,array $tagIds)
    {
        $this->app->dbAdapter->delete('PostHasPostTag',['postId'=>$postId,'postBlogId'=>$blogId]);
        foreach ($tagIds as $tagId) {
	        if($tagId == null || $tagId == 'null' ) continue;
            $this->app->dbAdapter->insert('PostHasPostTag',['postId'=>$postId,'postBlogId'=>$blogId,'postTagId'=>$tagId]);
        }
    }
}