<?php
/*
 * (c) Sascha Riexinger <sascha.riexinger@fmi.uni-stuttgart.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PHPWebAPI;

abstract class ADataBase
{
    protected abstract function sql($sql);
    protected abstract function lastID();
    protected abstract function getConnection();
}
