<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Src\CmsAdmin\Models\User;

class CustomerController extends Controller
{
    public function index(): View
    {
        // Every customer: the page's DataTable searches, sorts, filters and pages them in the browser.
        $customers = User::withCount('bookmarks')->latest()->get();

        return view('cms-admin::customers.index', compact('customers'));
    }
}
