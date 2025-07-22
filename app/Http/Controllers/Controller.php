<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController; // Important: aliasing the core Laravel controller

class Controller extends BaseController // Important: Extending the core Laravel controller
{
    use AuthorizesRequests, ValidatesRequests; // Important: Using these traits provides helper methods
}
