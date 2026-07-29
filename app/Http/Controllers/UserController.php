<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function impersonate(User $user)
    {
        auth()->user()->impersonate($user);

        return redirect()->route('dashboard');
    }

    public function leaveImpersonate()
    {
        auth()->user()->leaveImpersonation();

        return redirect()->route('dashboard');
    }

    public function addUserEventNote(Request $request, User $user)
    {
        $newnote = $user->event_notes()->create([
            'note' => $request->input('note'),
            'added_by' => auth()->user()->id,
        ]);

        $data = [
            'user_id' => $user->id,
            'note' => $newnote->note,
            'by' => $newnote->added_by_user->fullname,
            'formatted_date' => $newnote->created_at->format('m/d/Y g:i a'),
        ];

        return response()->json($data);

    }
}
