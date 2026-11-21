<?php

namespace Modules\Order\Enums;

enum IdempotencyKeyStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
}
