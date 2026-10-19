<?php

namespace App\Enums;

enum ItObjectKind: string
{
    case Physical = 'physical';
    case Virtual = 'virtual';
    case Room = 'room';
}
