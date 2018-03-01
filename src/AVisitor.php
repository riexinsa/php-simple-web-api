<?php

/*
 * (c) Sascha Riexinger <sascha.riexinger@fmi.uni-stuttgart.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PHPWebAPI;

/*
Abstract base class for a implementation of visiting all elements of an given collection
*/

abstract class AVisitor 
{
    /*
    Abstract method, this method will be invoked for any element in given collection.
    */
    protected abstract function visit($instance, &$element);


    /*
    Using this method the root elements of the given collection ($elements) will be visited by 
    calling the above defined abtract method.

    remark: The elements and element parameter are passed by reference and therefore may be
    changed in the called methods!
    */
    public function visitElements($instance, &$elements)
    {
        foreach($elements as &$element)
            $this->visit($instance, $element);
    }
}

?>