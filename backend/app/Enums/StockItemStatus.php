<?php

namespace App\Enums;

enum StockItemStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
