<?php

namespace App\Enums;

enum StockCategory: string
{
    case Equipment = 'equipment';
    case ProtectiveGear = 'protective_gear';
    case Supplies = 'supplies';
    case Merchandise = 'merchandise';
}
