<?php


namespace Bolt\Extension\Bolt\JsonApi\Converter\Parameter;

interface ParameterInterface
{

    public function convertRequest();

    public function findConfigValues();

    public function getParameter();
}

