<?php

namespace App\Enums;

enum DocumentType:string
{
    case OTHER = 'other';
    case CONTRACT = 'contract';
    case PLAN = 'plan';
    case DRAWING = 'drawing';
    case PERMIT = 'permit';
    case PHOTO = 'photo';
    case PDF = 'pdf';
    case WORD = 'word';
}
