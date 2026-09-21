<?php

namespace App\Enums;

enum TaxCalculationType: string
{
    case Revenue = 'revenue';
    case Profit = 'profit';
}
