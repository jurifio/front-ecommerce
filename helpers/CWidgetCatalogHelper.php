<?php

namespace bamboo\helpers;

use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductCategory;
use bamboo\core\theming\CWidgetHelper;


/**
 * Class CWidgetCatalogHelper
 * @package bamboo\app\helpers
 */
class CWidgetCatalogHelper extends CWidgetHelper
{
    /**
     * @param $paramKey
     * @param $paramVal
     * @param null $baseUrl
     * @return string
     */
    public function concatWithUrl($paramKey, $paramVal, $baseUrl = null)
    {
        //FIXME usa questo http_build_query
        return ($baseUrl ?? $this->presentUrl()) . "?" . $paramKey . "=" . "$paramVal";
    }

    /**
     * @param $paramKey
     * @param $paramVal
     * @param null $baseUrl
     * @param null $get
     * @return string
     */
    public function concatWithFullUrl($paramKey, $paramVal,$baseUrl = null,$get = null)
    {
        $get = $get ?? $this->app->router->request()->getRequestData();
        if (empty($get)) return $this->concatWithUrl($paramKey, $paramVal);
        $get[$paramKey] = $paramVal;
        ksort($get);
        $args = [];
        foreach ($get as $key => $val) {
            if(is_array($val)) {
                foreach ($val as $v) {
                    $args[] = $key . "=" . $v;
                }
            } else {
                $args[] = $key . "=" . $val;
            }

        }
        return ($baseUrl ?? $this->presentUrl()) . "?" . implode('&', $args);
    }

    /**
     * @return array
     */
    public function getActiveCatalogFilters()
    {
        $activeFilters = [];
        $filters = new \ArrayIterator($this->app->router->getMatchedRoute()->getComputedFilters());
        unset($filters['loc']);
        foreach ($filters as $key => $value) {
            if ($value !== '' && !mb_strstr($key, 'Id')) {
                $activeFilters[$key]['label'] = $value;
                $commonKey = $key;
            } else if ($value !== '' && mb_strstr($key, 'Id')) {
                $activeFilters[$commonKey]['id'] = $value;
            }
        }
        $parentCategory = $this->app->categoryManager->getCategoryParent($activeFilters['category']['id']);
        if ($parentCategory['depth'] == 0) {
            $activeFilters['rootCategory'] = $activeFilters['category'];
            unset($activeFilters['category']);
        }
        return $activeFilters;
    }

    /**
     * @param $value
     * @param $id
     * @param $type
     * @return bool|mixed|string
     */
    public function hrefAddFilter($value, $id, $type)
    {
        $localArgs = new \ArrayIterator($this->app->router->getMatchedRoute()->getComputedFilters());

        $link = $this->baseUrl();
        $link .= "/" . $localArgs['loc'];
        unset($localArgs['loc']);

        while ($localArgs->valid()) {
            $key = $localArgs->key();
            $val = $localArgs->current();
            $pre = "";
            switch ($key) {
                case 'loc':
                    break;
                case 'brand':
                    $brand = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($brand)) break;
                    $pre = "/";
                    $localArgs[$key . 'Id'] = '-b' . ($brand);
                    break;
                case 'color':
                    $color = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($color)) break;
                    $pre = "/colore-";
                    $localArgs[$key . 'Id'] = '-c' . ($color);
                    break;
                case 'size':
                    $size = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($size)) break;
                    $pre = "/taglia-";
                    $localArgs[$key . 'Id'] = '-s' . ($size);
                    break;
                case 'tag':
                    $size = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($size)) break;
                    $pre = "/tag-";
                    $localArgs[$key . 'Id'] = '-t' . ($size);
                    break;
                case 'tagExclusive':
                    $size = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($size)) break;
                    $pre = "/";
                    $localArgs[$key . 'Id'] = '-w' . ($size);
                    break;
                default:
                    $default = $type == $key ? $id : $localArgs[$key . 'Id'];
                    if (empty($default)) break;
                    $pre = "/";
                    $localArgs[$key . 'Id'] = '-' . ($default);
                    break;
            }
            $link .= $pre . ($type == $key ? $value : $val) . $localArgs[$key . 'Id'];
            unset($localArgs[$key . 'Id']);
            $localArgs->next();
        }
        $link = mb_strtolower($link, 'UTF-8');
        $link = str_replace(' ', '-', $link);
        $link = str_replace('\'', '', $link);
        return $link;
    }

    /**
     * @param $brand
     * @return string
     */
    public function hrefToCatalogBrand($brand) {
        if(is_numeric($brand)) {
            $brand = \Monkey::app()->repoFactory('Brand')->findOne([$brand]);
        }
        return $this->baseUrlLang().'/'.$brand->slug.'-b'.$brand->id;
    }

    /**
     * @param string $filterNameToRemove
     * @return bool|mixed|string
     */
    public function removeFilterFromCatalogUrl($filterNameToRemove)
    {
        $currentFilters = new \ArrayIterator($this->app->router->getMatchedRoute()->getComputedFilters());

        $link = $this->baseUrl() . "/" . $currentFilters['loc'];
        unset($currentFilters['loc']);

        while ($currentFilters->valid()) {

            $filterKey = $currentFilters->key();
            $filterValue = $currentFilters->current();

            if (empty($currentFilters[$filterKey]) || empty($currentFilters[$filterKey . 'Id']) || $filterKey == 'loc') {
                $currentFilters->next();
                continue;
            }

            $suffix = "/";
            $postfix = "-";
            switch ($filterKey) {
                case 'loc':
                    break;
                case 'brand':
                    $suffix = "/";
                    $postfix = "-b";
                    break;
                case 'color':
                    $suffix = "/colore-";
                    $postfix = "-c";
                    break;
                case 'size':
                    $suffix = "/taglia-";
                    $postfix = "-s";
                    break;
                case 'tag':
                    $suffix = "/tag-";
                    $postfix = "-t";
                    break;
                case 'tagExclusive':
                    $suffix = "/tagExclusive-";
                    $postfix = "-w";
                    break;
            }

            if ($filterKey == $filterNameToRemove) {
                if ($filterKey == 'category') {
                    $parentCategory = $this->app->categoryManager->getCategoryParent($currentFilters['categoryId']);
                    if ($parentCategory['depth'] == 0) {
                        $currentFilters->next();
                        continue;
                    } else {
                        $link .= $suffix . $parentCategory['slug'] . $postfix . $parentCategory['id'];
                    }
                } else {
                    unset($currentFilters[$filterKey . 'Id']);
                    $currentFilters->next();
                    continue;
                }
            } else {
                $link .= $suffix . $filterValue . $postfix . $currentFilters[$filterKey . 'Id'];
            }

            unset($default);
            unset($currentFilters[$filterKey . 'Id']);
            $currentFilters->next();
        }

        $link = mb_strtolower($link, 'UTF-8');
        $link = str_replace(' ', '-', $link);
        $link = str_replace('\'', '', $link);
        return $link;
    }

    /**
     * @param array $filters
     * @return bool|string
     */
    public function hrefCustomFilters(array $filters)
    {
        $localArgs = new \ArrayIterator($this->app->router->getMatchedRoute()->getComputedFilters());

        $link = $this->baseUrl();
        if (isset($filters['loc'])) {
            $link .= "/" . $filters['loc']['name'];
            unset($filters['loc']);
        } else {
            $link .= "/" . $localArgs['loc'];
        }

        foreach ($filters as $key => $filter) {
            $pre = "";
            switch ($key) {
                case 'loc':
                    break;
                case 'brand':
                    if (!empty($filter)) {
                        $pre = "/";
                        $link .= $pre . $filter['slug'] . '-b' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/";
                        $link .= $pre . $localArgs[$key] . '-b' . $localArgs[$key . 'Id'];
                    }
                    break;
                case 'color':
                    if (!empty($filter)) {
                        $pre = "/colore";
                        $link .= $pre . $filter['slug'] . '-c' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/colore";
                        $link .= $pre . $localArgs[$key] . '-c' . $localArgs[$key . 'Id'];
                    }
                    break;
                case 'size':
                    if (!empty($filter)) {
                        $pre = "/taglia";
                        $link .= $pre . $filter['slug'] . '-s' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/taglia";
                        $link .= $pre . $localArgs[$key] . '-s' . $localArgs[$key . 'Id'];
                    }
                    break;
                case 'tag':
                    if (!empty($filter)) {
                        $pre = "/tag";
                        $link .= $pre . $filter['slug'] . '-t' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/tag";
                        $link .= $pre . $localArgs[$key] . '-t' . $localArgs[$key . 'Id'];
                    }
                    break;
                case 'tagExclusive':
                    if (!empty($filter)) {
                        $pre = "/tagExclusive";
                        $link .= $pre . $filter['slug'] . '-w' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/tagExclusive";
                        $link .= $pre . $localArgs[$key] . '-w' . $localArgs[$key . 'Id'];
                    }
                    break;
                default:
                    if (!empty($filter)) {
                        $pre = "/";
                        $link .= $pre . $filter['slug'] . '-' . $filter['id'];
                    } elseif (!empty($localArgs[$key]) && !empty($localArgs[$key . 'Id'])) {
                        $pre = "/";
                        $link .= $pre . $localArgs[$key] . '-' . $localArgs[$key . 'Id'];
                    }
                    break;
            }

        }
        $link = mb_strtolower($link, 'UTF-8');
        $link = str_replace(' ', '-', $link);
        $link = str_replace('\'', '', $link);
        return $link;
    }

    /**
     * @param CProduct $product
     * @return string
     */
    public function getLinkToDetailsPage(CProduct $product)
    {
        return $this->baseUrlLang() . '/' . $product->productBrand->slug . '/p/' . $product->id . '/v/' . $product->productVariantId;
    }

    /**
     * @param CProductCategory $category
     * @return string
     */
    public function getLinkToCategoryPage(CProductCategory $category)
    {
        return $this->baseUrlLang() . '/' . $category->slug . "-" . $category->id;
    }
}