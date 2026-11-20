<?php

namespace Modules\Promotion\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
