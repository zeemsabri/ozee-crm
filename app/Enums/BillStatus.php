<?php
  
namespace App\Enums;

enum BillStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Void = 'void';
}
