<?php

abstract class ADataBase
{
    protected abstract function sql($sql);
    protected abstract function lastID();
    protected abstract function getConnection();
}
