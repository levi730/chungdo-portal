<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Committee;

/**
 * Committee administration (super.admin only, via the manage-users ability on
 * the route group). The index embeds a Livewire table; create/edit embed the
 * CommitteeForm Livewire component, which handles persistence and membership.
 */
class CommitteeController extends Controller
{
    public function index()
    {
        return view('admin.committees.index');
    }

    public function create()
    {
        return view('admin.committees.create');
    }

    public function edit(Committee $committee)
    {
        return view('admin.committees.edit', ['committee' => $committee]);
    }
}
