<?php

namespace App\Dto;

enum ShippingTaskType: string
{
    case Import = 'Import';
    case Export = 'Export';
}
