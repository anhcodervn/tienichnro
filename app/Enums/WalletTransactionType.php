<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Deposit = 'credit';
    case Purchase = 'debit';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
