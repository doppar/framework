<?php

namespace Phaseolies\Database\Entity\Casts;

enum Type: string
{
    case Integer    = 'integer';
    case Float      = 'float';
    case Boolean    = 'boolean';
    case String     = 'string';
    case Array      = 'array';
    case Object     = 'object';
    case Collection = 'collection';
    case DateTime   = 'datetime';
    case Date       = 'date';
    case Timestamp  = 'timestamp';
    case Json       = 'json';
}
