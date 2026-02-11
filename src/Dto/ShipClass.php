<?php

namespace App\Dto;

enum ShipClass: string
{
    case LCB = 'LCB';
    case WCB = 'WCB';
    case VCB = 'VCB';

    public function weightCapacity(): float
    {
        return match ($this) {
            self::LCB => 2000.0,
            self::WCB => 3000.0,
            self::VCB => 1000.0,
        };
    }

    public function volumeCapacity(): float
    {
        return match ($this) {
            self::LCB => 2000.0,
            self::WCB => 1000.0,
            self::VCB => 3000.0,
        };
    }
}
