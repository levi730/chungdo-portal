<?php

namespace App\Http\Controllers;

use App\Models\Committee;

/**
 * The association's committees, for members.
 *
 * Deliberately inside the auth group rather than alongside the public school
 * directory: the page carries members' email addresses and phone numbers, and
 * those are people's personal details, not an organisation's. `/schools` is
 * public because a school's phone number belongs to the school.
 *
 * Editing lives in Admin\CommitteeController, which is super.admin only.
 */
class CommitteeController extends Controller
{
    public function index()
    {
        $committees = Committee::with(['members.school'])
            ->orderBy('name')
            ->get();

        return view('committee.index', compact('committees'));
    }

    /**
     * One committee, by the slug the admin form has always written and nothing
     * has ever used — so a committee can be linked to directly.
     */
    public function show(Committee $committee)
    {
        $committee->load(['members.school']);

        return view('committee.show', compact('committee'));
    }
}
