<?php

namespace App\Domain\Enums;

enum AttachmentCategory: string
{
    case Agreement = 'agreement';
    case Receipt = 'receipt';
    case Statement = 'statement';
    case Message = 'message';
    case Photo = 'photo';
    case Other = 'other';
}
