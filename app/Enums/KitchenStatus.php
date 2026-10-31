<?php

namespace App\Enums;

/**
 * Where an order item stands in the kitchen. The kitchen display moves it
 * beyond Sent from v0.5.0.
 */
enum KitchenStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Voided = 'voided';
}
