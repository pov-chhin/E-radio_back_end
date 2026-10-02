<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StationResource;
use Src\RadioSceaper\Models\RadioSceaperStation;

class StationController extends Controller
{
    public function index()
    {
        // "ams"-tagged stations (the station owner's own brand) pin first,
        // via the existing free-text `tags` field — no schema change needed.
        $stations = RadioSceaperStation::where('is_published', true)
            ->orderByRaw("CASE WHEN tags LIKE '%ams%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return StationResource::collection($stations);
    }
}
