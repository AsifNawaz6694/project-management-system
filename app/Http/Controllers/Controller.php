<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize(), so object-level policy checks
    // are available on the same footing as route middleware.
    use AuthorizesRequests;
}
