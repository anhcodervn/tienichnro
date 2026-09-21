<?php

namespace App\Enums;

enum TopupProviderType: string
{
    case MerchantPartnerCard = 'merchant_partner_card';
    case AccNro = 'accnro';
    case Manual = 'manual';
}
