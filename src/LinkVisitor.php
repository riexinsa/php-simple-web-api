<?php

/*
 * (c) Sascha Riexinger <sascha.riexinger@fmi.uni-stuttgart.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PHPWebAPI;

use PHPWebAPI\AVisitor;

class LinkVisitor extends AVisitor
{
    private $links = array();
    private $debug = false;

    private function __construct(){}

    public static function create()
    {
        return new LinkVisitor();
    }

    private function write($string)
    {           
        if($this->debug)
        {
            if(is_array($string))
                print_r($string);
            else
                print($string);
        }
    }

    protected function addLink(&$to, $rel, $href, $method = "GET", $type = "application/json")
    {
        $toAdd = array();
        $toAdd["rel"] = $rel;
        $toAdd["href"] = $href;
        $toAdd["method"] = $method;
        $toAdd["type"] = $type;
        array_push($to, $toAdd);
    }


    public function addLinkReference($transformPath, $rel, $href, $method = "GET", $type = "application/json", $dependency = null)
    {
        if(array_key_exists($transformPath, $this->links))
            $toPushIn = $this->links[$transformPath];
        else 
            $toPushIn = array();
        array_push($toPushIn, array(
            "rel" => $rel,
            "href" => $href,
            "method" => $method,
            "type" => $type,
            "dep" => $dependency
        ));
        $this->links[$transformPath] = $toPushIn;
        return $this;
    }

    private function visitElement($instance, $path, &$element, $links)
    {
        $this->write("******************************************* visitElement - path\n");
        $this->write($path);
        $this->write("******************************************* visitElement - element\n");
        $this->write($element);
        $this->write("******************************************* visitElement - first\n");
        $first = array_shift($path);
        $this->write("$first\n");
        if($first == "")
        {
            $this->write("go into element itself\n");
            $this->visit($instance, $element, $links);
        }
        else 
        {
            if($first == "*")
            {
                $this->write("exec for each \n");
                foreach($element as &$ele)
                    $this->visit($instance, $ele, $links);
            }
            else 
            {
                $this->write("go deeper into element \n");
                if(array_key_exists($first, $element))
                    $this->visitElement($instance, $path, $element[$first], $links);
            }
        }
    }

    public function visitElements($instance, &$elements)
    {
        $this->write("~~~~~~~~~~~~~~~~~~~~~~~~~~~ visitElements\n");
        $this->write($elements);
        foreach($this->links as $path => $links_for_path)
        {
            $pathParts = explode("/", trim($path, "/"));
            $this->write($pathParts);
            $this->visitElement($instance, $pathParts, $elements, $links_for_path);
        }
    }

    public function visit($instance, &$element, $links_for_path)
    {
        $this->write("\n---------------before---------------------\n");
        $this->write($element);
        $this->write("\n---------------before---------------------\n");
        if(array_key_exists("_links", $element))
            $links = $element["_links"];
        else
            $links = array();
        foreach($links_for_path as $link)
        {
            $add = true;
            $dep = $link["dep"];
            if($dep != null)
            {   
                if(is_string($dep))
                {
                    if(!array_key_exists($dep, $element) || $element[$dep] == null)
                        $add = false;
                }
                else 
                    $add = $dep($element);
            }
            if($add)
            {
                $this->addLink(
                    $links, 
                    $link["rel"], 
                    $instance->fillPlaceHolders($link["href"], $element),
                    $link["method"], 
                    $link["type"]);
            }
        }
        if(is_array($element) && array_key_exists(0, $element))
            $element = array("_embedded" => $element);
        if(sizeof($links) != 0)      
            $element["_links"] = $links;
        $this->write("\n---------------after---------------------\n");
        $this->write($element);
        $this->write("\n---------------after---------------------\n");
    }
}



?>