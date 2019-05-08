<?php

namespace bamboo\domain\entities;

use bamboo\core\exceptions\RedPandaPaginationException;

/**
 * Class CPagination
 * @package bamboo\app\domain\entities
 */
class CPagination
{
    /**
     * present page
     * @var int
     */
    private $page;
    /**
     * n° of element shown in 1 page
     * @var int
     */
    private $pageElem;
    /**
     * n° of all elements
     * @var int
     */
    private $totalItems;

    /**
     * @param int $totalItems total number of element to paginate
     * @param int $defaultItemNumberPerPage you can overwrite the number of elements per page
     * @param array $get to fetch the page changing
     *
     * @throws RedPandaPaginationException
     */
    public function __construct($totalItems, $defaultItemNumberPerPage = 30, $get = null)
    {
        if (!is_numeric($totalItems) || $totalItems < 0) {
            throw new RedPandaPaginationException(sprintf("Invalid number for max elements: %s",$totalItems), 100);
        }

        $this->totalItems = $totalItems;
        if ($get != null) {
            if (isset($get["page"])) {
                $page = $get["page"] - 1;
            }

            if (isset($get["nelem"])) {
                $pageElem = $get["nelem"];
            }
        }
        //setting page and element per page if argument default are valid, else global default (0-30)
        $this->page = !isset($page) ? 0 : $page;
        $this->pageElem = !isset($pageElem) ? $defaultItemNumberPerPage : $pageElem;
    }

    /**
     * @param int $startingPage
     * @return int present page number for front end
     */
    public function current($startingPage = 1)
    {
        return ($this->page+$startingPage);
    }

    /**
     * @return int
     */
    public function totalItems(){
        return $this->totalItems;
    }

    /**
     * @return int n° of element shown in 1 page
     */
    public function pageElem()
    {
        return ($this->pageElem);
    }

    /**
     * @param int $nlem
     */
    public function changePageElem($nlem)
    {
        $this->pageElem = $nlem;
    }

    /**
     * @return int
     */
    public function numElem()
    {
        return ($this->totalItems);
    }

    /**
     * @return mixed
     */
    public function offset()
    {
        return ($this->pageElem * $this->page);//+1??
    }

    /**
     * @return int
     */
    public function maxPage()
    {
        return (ceil(($this->totalItems / $this->pageElem)));
    }

    /**
     * @param int $startingPage
     * @return bool|int
     */
    public function prev($startingPage = 1)
    {
        if ($this->page==0) {
            return false;
        }

        return (($this->page-1)+$startingPage);
    }

    /**
     * @param int $startingPage
     * @return bool|int
     */
    public function next($startingPage = 1)
    {
        if ($this->maxPage($startingPage) == $this->current($startingPage)) {
            return false;
        }

        return ($this->current($startingPage)+1);
    }

    /**
     * @param $newPage
     * @param int $startingPage
     * @throws RedPandaPaginationException
     */
    public function changePage($newPage, $startingPage=1)
    {
        if ($newPage > 0 && $newPage <= $this->maxPage($startingPage)) {
            $this->page = ($newPage - $startingPage);
            return;
        }

        throw new RedPandaPaginationException("Invalid parameter ");
    }
}