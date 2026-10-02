<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Src\CmsAdmin\Models\User;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = User::withCount('bookmarks')->latest()->paginate(15);

        return view('cms-admin::customers.index', compact('customers'));
    }
}
