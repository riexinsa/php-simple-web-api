<?php
/*
 * (c) Sascha Riexinger <sascha.riexinger@fmi.uni-stuttgart.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PHPWebAPI;

class APIController
{
    private static $instance = null;

    // table for response request pairs
    private $requestTable = array();
    // authentification class
    private $auth = null;
    private $noCheckFor = array();
    // data base link
    private $db = null;
    // request if not PATH_INFO
    private $request = null;
    // base URL
    private $baseURI = "";
   
    // the actual informations
    private $input = "";
    private $headers = "";
    private $pathElements = "";
    private $table = "";
    private $method = "";

    // options requests
    private $fullfillAllOptionsRequests = false;

    public static function getInstance()
    {
        if(self::$instance == null)
            self::$instance = new APIController();
        return self::$instance;
    }

    private function __construct()
    {
    }

    /*******************************************************************************************************************
    registration methods
    *******************************************************************************************************************/

    // fct must return an array containing:
    // array( "http_response_code" => ..., "content" => ...)
    // if content is a string it will be handled as error and mangled into array("error" => content) before returning
    // otherwise it will be returned as it is.
    public function addRequestProcessing($table, $requestType, $fct, $requestpattern, $visitor = null)
    {
        if($visitor != null && !is_a($visitor, "PHPWebAPI\AVisitor"))
            throw new \Exception("Wrong type of visitor! AVisitor expected.");
        if($requestpattern != null)
            $rp = explode("/", trim($requestpattern, "/"));
        else
            $rp = array();
        $this->requestTable[$table][$requestType] = array("fct" => $fct, "rp" => $rp, "vis" => $visitor);
    }

    public function setAuthentification($auth, $route = "login", $method = "POST")
    {   
        if(is_a($auth, "PHPWebAPI\AAuthentification"))
        {
            $this->auth = $auth;
            array_push($this->noCheckFor, $route);
            $this->addRequestProcessing
            (
                $route, $method, 
                function($instance) use($auth)
                {
                    $res = $auth->login($instance);
                    if(is_string($res))
                        return $this->httpResponse(401, json_encode(array("error" => $res)));
                    return $this->httpResponse(200, null, $res);
                },
                ""
            );
        }
        else 
            throw new \Exception("Wrong type for authentification! AAuthentification expected.");
    }

    public function registerDataBaseHandler($db)
    {
        //print($db);
        if(is_a($db, "PHPWebAPI\ADataBase"))
            $this->db = $db;
        else 
        {
            throw new \Exception("Wrong type of database! DataBase expected.");
        }
    }

    public function httpResponse($code, $content, $headers = array())
    {
        return array("http_response_code" => $code, "content" => $content, "headers" => $headers);
    }

    public function fillPlaceHolders($where, $with = null)
    {
        if($with == null)
            $vars = $this->pathElements;
        else
            $vars = $with;
        $result = preg_replace_callback("/\{\{([a-z0-9_]+)((\[[0-9]+\])*)\}\}/i", function($matches) use ($vars)
        {
            $name = $matches[1];
            $res = $vars[$name];
            if($matches[2] != null)
            {   
                preg_match_all("/(\[([0-9])+\])/", $matches[2], $m);
                for($i=0;$i<sizeof($m[2]);$i++)
                    $res = $res[$m[2][$i]];
                return $res;
            }
            else
                return $res;
        }, $where);
        return $result;
    }

    private function createSQLSetter()
    {
        $link = $this->db->getConnection();
        $columns = preg_replace('/[^a-z0-9_]+/i','',array_keys($this->input));
        $values = array_map(function ($value) use ($link) 
        {
            if ($value===null) 
  	            return null;
            return mysqli_real_escape_string($link,(string)$value);
        },array_values($this->input));

        $set = '';
        for ($i=0;$i<count($columns);$i++) 
        {
            $set.=($i>0?',':'').'`'.$columns[$i].'`=';
            $set.=($values[$i]===null?'NULL':'"'.$values[$i].'"');
        }
        return $set;
    }

    public function transformResults(&$results)
    {
        $visitor = $this->requestTable[$this->table][$this->method]["vis"];
        if($visitor == null)
            return;
        $visitor->visitElements($this, $results);
    }

    private function executeRequest($request)
    {
        $subArray = $this->requestTable[$this->table];
		$fct = $subArray[$this->method]["fct"];
        $rp = $subArray[$this->method]["rp"];
        // values from the path
        $this->pathElements = array();
        foreach($rp as $val)
            $this->pathElements[$val] = array_shift($request);

        if(is_string($fct))
        {
            $this->pathElements["set"] = $this->createSQLSetter($this->input);
            $sql = self::fillPlaceHolders($fct);
            $res = $this->db->sql($sql);
            $this->transformResults($res);
            return  $this->httpResponse(200, json_encode($res));
        }
        else
        {
            return $fct($this);
        }
    }
    /*******************************************************************************************************************
    setters and getters
    *******************************************************************************************************************/
    public function getCurrentInput()
    {
        return $this->input;
    }
    
    public function getCurrentPathElements()
    {
        return $this->pathElements;
    }

    public function getCurrentHeaders()
    {
        return $this->headers;
    }

    public function getDataBase()
    {
        return $this->db;
    }

    public function getCurrentTable()
    {
        return $this->table;
    }

    public function getCurrentMethod()
    {
        return $this->method;
    }

    public function setBaseURI($baseURI)
    {
        $this->baseURI = $baseURI;
    }    

    public function fullfillAllOptionsRequests($value)
    {
        $this->fullfillAllOptionsRequests = $value;
    }

    public function addUnCheckedEndPoint($name)
    {
        array_push($this->noCheckFor, $name);
    }

    /*******************************************************************************************************************
    login handlers
    *******************************************************************************************************************/
    private function checkToken($headers)
    {
        if($this->auth == null)
            return true;
        return $this->auth->checkToken($headers);
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function setRequest($request)
    {
        $this->request = $request;
    }

    /*******************************************************************************************************************
    run method to do it all ;)
    *******************************************************************************************************************/
    public function run()
    {
        if($this->method == "")
            $this->method = $_SERVER["REQUEST_METHOD"];

        if($this->fullfillAllOptionsRequests && $this->method == "OPTIONS")
        {
            http_response_code(200);
            return;
        }

        $pathInfo = "";
        if($this->request != null)
            $pathInfo = trim($this->request, "/");
        else 
            $pathInfo = trim($_SERVER["PATH_INFO"], "/");

        $request = explode("/", $pathInfo);
        $this->input = json_decode(file_get_contents('php://input'),true);
        $this->headers = getallheaders();
        $this->table = preg_replace('/[^a-z0-9_]+/i','',array_shift($request));

        if(!in_array($this->table, $this->noCheckFor))
        {
            if(!$this->checkToken($this))
            {
                http_response_code(403);
                echo json_encode(array("error" => "authorization failed"));
                return;
            }
        }
        if(!array_key_exists($this->table, $this->requestTable)  || !(array_key_exists($this->method, $this->requestTable[$this->table])))
        {
            http_response_code(404);
            echo json_encode(array("error" => "unknown route [$this->method] $this->table"));
            return;
        }
        $res = $this->executeRequest($request);
        foreach($res["headers"] as $key => $value)
        {
            header("$key: $value");
        }
        if(!array_key_exists("Content-Type", $res["headers"]))
            header("Content-Type: application/json");
        http_response_code($res["http_response_code"]);
        print $res["content"];
    }
}

?>