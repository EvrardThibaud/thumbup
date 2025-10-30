<?php
namespace App\Enum;

enum OrderStatus: string
{
    case CREATED   = 'created';   // client created, awaiting admin decision
    case REFUSED   = 'refused';   // admin refused, kept for history
    case CANCELED  = 'canceled';  // client canceled while still created
    case ACCEPTED  = 'accepted';  // current "To do" (Admin sees “To do”, client sees “Accepted”)
    case DOING     = 'doing';     // work in progress
    case DELIVERED = 'delivered'; // work finished and shown to client
}
