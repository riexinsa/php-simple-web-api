<?php

/*
 * (c) Sascha Riexinger <sascha.riexinger@fmi.uni-stuttgart.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PHPWebAPI;

abstract class AAuthentification
{
    // return values:
    // string => error description
    // array => (header entry as string => token as string) => ok token returned
    // instance is an instance of TableProcessor
    protected abstract function login($instance);
    // false on error
    // true on success
    // instance is an instance of TableProcessor
    protected abstract function checkToken($instance);
}

?>