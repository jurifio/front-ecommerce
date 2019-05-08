<?php

namespace bamboo\ecommerce\business;

use bamboo\core\application\AApplication;
use bamboo\core\io\CJsonAdapter;

class CTemplateRoutes
{
    /**
     * @var AApplication
     */
    protected $app;
    /**
     * @var CJsonAdapter
     */
    protected $routes;

    /**
     * @var bool | array
     */
    protected $data;

    public function __construct(AApplication $app)
    {
        $this->app = $app;
        $this->routes = new CJsonAdapter($this->app->rootPath().$this->app->cfg()->fetch("paths","store-theme").'/layout/routes.json');
    }

    public function check($keyToCheck, $recursive = true)
    {
        $return = [];

        if (isset($this->routes->getFullTree()[$keyToCheck]['template'])) {
            $return[$keyToCheck]['template']['filename'] = $this->routes->getFullTree()[$keyToCheck]['template']['name'];
            $return[$keyToCheck]['template']['file_exists'] = file_exists($this->app->cfg()->fetch("paths","store-theme").'/widgets/'.$this->routes->getFullTree()[$keyToCheck]['template']['name']);
            $return[$keyToCheck]['template']['json_exists'] = file_exists($this->app->cfg()->fetch("paths","app").'/data/widget/'.strtolower(substr(explode('.',$keyToCheck)[0],1)).'.it.json');
        } else {
            $return[$keyToCheck]['template'] = 'not set';
        }

        /**
         * Recursive child check
         */
        if (isset($this->routes->getFullTree()[$keyToCheck]['children'])) {
            foreach ($this->routes->getFullTree()[$keyToCheck]['children'] as $key => $value) {
                $arr = array_reverse(explode('.',$value));
                $dataKey = $arr[0];
                array_shift($arr);
                $tpl = implode('.',array_reverse($arr));
                $return[$keyToCheck]['children'][$value]['key_exists'] = isset($this->routes->getFullTree()[$tpl]);
                $return[$keyToCheck]['children'][$value]['data_key'] = $dataKey;
                if ($recursive) {
                    $rcheck = $this->check($tpl);
                    $return[$keyToCheck]['children'][$value]['key_has_children'] = (count($rcheck)>0) ? $rcheck : 'undefined';
                }
            }
        }

        return $return;
    }

    public function draw($data, $embedStyle = false)
    {
        $style = "<style>
            * { font-family: \"Courier New\", Courier, monospace; font-size: 14px;}
            ul, li { margin-left: 10px; padding-left: 0; }
            .green { color:#008200; font-weight: bolder}
            .red { color:#820000; font-weight: bolder}
            .black { color:#000000; font-weight: bolder}
        </style>";

        if ($embedStyle) {
            echo $style;
        }

        foreach ($data as $key => $value) {
            echo "<ul>";
            if (is_array($value)) {
                echo "<h1>$key</h1><ul>";
                foreach ($value as $subkey => $val) {
                    if (!is_array($val)) {
                        switch ($val) {
                            case 1:
                                $val = "<span class=\"green\">ok</span>";
                                break;
                            case null:
                                $val = "<span class=\"red\">fail</span>";
                                break;
                            default:
                                $val = "<span class=\"black\"> $val</span>";
                                break;
                        }
                        echo "<li><strong>$subkey:</strong> $val</li>";
                    }
                }
                $this->draw($value);
                echo "</ul>";
            }
            echo "</ul>";
        }
    }
}