<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StationResource;
use Src\RadioSceaper\Models\RadioSceaperStation;

class StationController extends Controller
{
    public function index()
    {
        $stations = RadioSceaperStation::where('is_published', true)
            ->inListenerOrder()
            ->get();

        return StationResource::collection($stations);
    }
}
