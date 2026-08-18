<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Enums\Roles;

class MetaController extends ApiBaseController
{
    public function roles()
    {
        return $this->sendResponse(HTTP::OK, Roles::values(), 'Roles recuperados exitosamente');
    }
}
