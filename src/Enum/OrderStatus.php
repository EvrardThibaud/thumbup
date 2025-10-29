<?php
namespace App\Enum;

enum OrderStatus: string
{
    case TODO = 'todo';
    case DOING = 'doing';
    case DELIVERED = 'delivered';
    case PAID = 'paid';
}
