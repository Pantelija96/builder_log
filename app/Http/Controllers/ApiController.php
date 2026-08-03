<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    use ApiResponse;
}
