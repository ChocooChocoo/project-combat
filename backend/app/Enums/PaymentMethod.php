<?php

namespace App\Enums;

/** How a payment was made (R-34, R-35). PayMongo covers GCash, Maya and card online. */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Ewallet = 'ewallet';
    case Paymongo = 'paymongo';
}
