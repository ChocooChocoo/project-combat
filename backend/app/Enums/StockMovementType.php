<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Sale = 'sale';

    public function direction(): int
    {
        return match ($this) {
            self::Opening, self::Receipt, self::Return => 1,
            self::Issue, self::Damaged, self::Lost, self::Sale => -1,
            self::Adjustment => 0,
        };
    }

    public function needsNote(): bool
    {
        return in_array($this, [self::Adjustment, self::Damaged, self::Lost], true);
    }

    public static function manual(): array
    {
        return ['receipt', 'issue', 'return', 'adjustment', 'damaged', 'lost'];
    }
}
