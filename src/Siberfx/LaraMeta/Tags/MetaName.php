<?php
namespace Siberfx\LaraMeta\Tags;

class MetaName extends TagAbstract
{
    public static function tagDefault($key, $value)
    {
        return '<meta name="'.$key.'" content="'.$value.'" />';
    }
}
