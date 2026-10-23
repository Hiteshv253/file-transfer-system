<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Common\Core\BaseController;
use Common\Files\Actions\GetUserSpaceUsage;
use Illuminate\Http\JsonResponse;

class SpaceUsageController extends BaseController
{
    public function index(): JsonResponse
    {
        $this->authorize('show', Auth::user());

        $usage = (new GetUserSpaceUsage(uploadType: 'bedrive'))->execute();

        return $this->success($usage);
    }
}
