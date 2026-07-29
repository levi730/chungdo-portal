<?php

namespace App\Http\Controllers;

use App\Models\AccountLinkRequest;
use App\Models\User;
use App\Notifications\AccountLinkRequested;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    /**
     * Display the Edit Profile page
     *
     * @return \Illuminate\View\View
     */
    public function editProfile()
    {

        $schools = \App\Models\School::orderBy('name')->get();
        $ranks = \App\Models\Rank::orderBy('id')->get();
        $devices = \DB::table('sessions')->where('user_id', \Auth::user()->id)->get()->reverse();

        return view('profile.edit', compact('schools', 'ranks', 'devices'));
    }

    /**
     * Update the Avatar
     *
     * @return \Illuminate\Http\Response
     */
    public function updateAvatar(Request $request, $id = null)
    {

        // validate
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:15360|dimensions:min_width=200,min_height=200',
        ]);

        // remove old avatar from storage
        $this->removeOldAvatar(true, $id);

        $success_key = 'avatar-success-main';

        // process avatar and redirect back
        if ($id) {
            $success_key = 'avatar-success-modal';
            session()->flash('profile_hash', 'tabs-student');
        }
        if ($this->processAvatar($request, $id)) {
            if ($request->wantsJson()) {
                $user = ($id) ? User::find($id) : \Auth::user();

                return response()->json([
                    'success' => 'Updated the avatar successfully!',
                    'newsrc' => $user->avatar,
                ], 201);
            } else {
                return \Redirect::back()->with($success_key, 'Updated the avatar successfully!');
            }
        } else {

            if ($request->wantsJson()) {
                return response()->json([
                    'errors' => ['avatar' => ['Failed to update the avatar']],
                ], 500);
            } else {
                return \Redirect::back()->withErrors(['avatar', 'Failed to update the avatar']);
            }
        }
    }

    /**
     * Process the resize and storage of the image
     *
     * @return bool
     */
    private function processAvatar(Request $request, $id = null)
    {

        // get file
        $file = $request->file('avatar');
        // get filename name with extension
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // remove unwanted characters
        $filename = preg_replace('/[^A-Za-z0-9 ]/', '', $filename);
        $filename = preg_replace("/\s+/", '-', $filename);
        // create unique file name
        $uniqueFileName = substr(md5($filename), 0, 15).'_'.time().'.'.$file->getClientOriginalExtension();

        // resize avatar
        $resize = Image::make($file)->fit(400, null, function ($constraint) {
            $constraint->upsize();
        })->encode('png');
        // save avatar to public storage
        $save = \Storage::put("public/avatars/{$uniqueFileName}", $resize->__toString());

        // if avatar has been stored successfully
        if ($save) {
            // update user table
            $user = ($id) ? User::find($id) : \Auth::user();
            $user->avatar = $uniqueFileName;
            $user->save();

            // return success
            return true;
        } else {
            return false;
        }
    }

    public function deleteAvatar($id = null)
    {
        $this->removeOldAvatar($id);

        $success_key = 'avatar-success-main';
        if ($id) {
            $success_key = 'avatar-success-modal';
            session()->flash('profile_hash', 'tabs-student');
        }

        if (\Request::wantsJson()) {
            $user = ($id) ? User::find($id) : \Auth::user();

            return response()->json([
                'success' => 'Deleted the avatar successfully!',
                'newsrc' => $user->avatar,
            ], 201);
        } else {
            return \Redirect::back()->with($success_key,
                'The avatar has been deleted successfully!');
        }
    }

    /**
     * Remove avatar currently in use
     *
     * @param  $internalRequest
     * @return bool
     * @return \Illuminate\Http\Response
     */
    public function removeOldAvatar($id = null)
    {
        $user = ($id) ? User::find($id) : \Auth::user();
        //dd($user, $internalRequest, $id);
        // if user has an avatar currently in use
        if ($user->avatar) {
            // delete avatar from storage
            \Storage::delete('public/avatars/'.$user->avatar);
        }
        $user->avatar = null;
        $user->save();

        return true;

    }

    /**
     * Remove unused device
     *
     * @return \Illuminate\Http\Response
     */
    public function removeDevice(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-active-sessions');
        $delete = \DB::table('sessions')->where('id', $id)->delete();

        return \Redirect::back()->with('tab-device-success', 'The device has been deleted successfully!');
    }

    public function updateFamilyMember(Request $request, $id)
    {
        $input = $request->all();
        session()->flash('profile_hash', 'tabs-student');

        Validator::make($input, [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
        ])->validateWithBag('updateFamilyMember');

        $u = User::findOrFail($id);
        abort_unless(\Auth::user()->canManage($u), 403);

        $u->forceFill([
            'firstname' => $input['firstname'],
            'lastname' => $input['lastname'],
            'school_id' => $input['school_id'],
            'rank_id' => $input['rank_id'],
            'dob' => ($input['dob']) ? new Carbon($input['dob']) : null,
            'height' => $input['height'],
            'weight' => $input['weight'],
            'sex' => $input['sex'],
        ])->save();

        return \Redirect::back()->with('tabs-school-success', "'{$u->full_name}' has been updated successfully!");
    }

    public function deleteFamilyMember(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-student');

        $u = User::findOrFail($id);
        abort_unless(\Auth::user()->canManage($u), 403);
        // A person with their own login is a real account, not a dependent to
        // be removed from someone else's family list.
        abort_if($u->can_login, 403, 'A login-enabled account cannot be deleted from here.');

        $u->delete();

        return \Redirect::back()->with('tabs-school-success', "'{$u->full_name}' was deleted successfully!");
    }

    public function inviteFamilyMember(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-student');

        $target = User::findOrFail($id);
        abort_unless(\Auth::user()->canManage($target), 403);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
        ]);

        if ($validator->fails()) {
            return \Redirect::back()->withErrors($validator, 'updateFamilyMember')->withInput();
        }

        $target->promoteToLoginableAdult($validator->validated()['email'], \Auth::user());

        // Let them choose their own password; the reset link doubles as the invite.
        Password::sendResetLink(['email' => $target->email]);

        return \Redirect::back()->with('tabs-school-success', "An invitation to log in was sent to {$target->email}.");
    }

    public function requestAccountLink(Request $request)
    {
        session()->flash('profile_hash', 'tabs-student');
        $me = \Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);
        $validator->after(function ($validator) use ($request, $me) {
            $recipient = User::where('email', $request->input('email'))->where('can_login', 1)->first();

            if (! $recipient) {
                $validator->errors()->add('email', 'No account was found with that email address.');
            } elseif ($recipient->is($me)) {
                $validator->errors()->add('email', 'You cannot link an account to itself.');
            } elseif ($me->isLinkedWith($recipient)) {
                $validator->errors()->add('email', 'Your accounts are already linked.');
            } elseif ($me->linkRequestsSent()->where('recipient_user_id', $recipient->id)->where('status', AccountLinkRequest::STATUS_PENDING)->exists()) {
                $validator->errors()->add('email', 'A request to that account is already pending.');
            }
        });

        if ($validator->fails()) {
            return \Redirect::back()->withErrors($validator, 'updateFamilyMember')->withInput();
        }

        $recipient = User::where('email', $request->input('email'))->where('can_login', 1)->first();

        AccountLinkRequest::create([
            'requester_user_id' => $me->id,
            'recipient_user_id' => $recipient->id,
        ]);

        $recipient->notify(new AccountLinkRequested($me));

        return \Redirect::back()->with('tabs-school-success', "A link request was sent to {$recipient->full_name}. They'll need to accept it.");
    }

    public function acceptAccountLink(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-student');

        $link = AccountLinkRequest::findOrFail($id);
        // Only the recipient may accept, and only while it is still pending.
        abort_unless($link->recipient_user_id === \Auth::user()->id && $link->isPending(), 403);

        $link->requester->linkAccountWith($link->recipient);
        $link->update(['status' => AccountLinkRequest::STATUS_ACCEPTED, 'responded_at' => now()]);

        return \Redirect::back()->with('tabs-school-success', "You are now linked with {$link->requester->full_name}.");
    }

    public function declineAccountLink(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-student');

        $link = AccountLinkRequest::findOrFail($id);
        abort_unless($link->recipient_user_id === \Auth::user()->id && $link->isPending(), 403);

        $link->update(['status' => AccountLinkRequest::STATUS_DECLINED, 'responded_at' => now()]);

        return \Redirect::back()->with('tabs-school-success', 'The link request was declined.');
    }

    public function cancelAccountLink(Request $request, $id)
    {
        session()->flash('profile_hash', 'tabs-student');

        $link = AccountLinkRequest::findOrFail($id);
        // Only the requester may cancel their own still-pending request.
        abort_unless($link->requester_user_id === \Auth::user()->id && $link->isPending(), 403);

        $link->update(['status' => AccountLinkRequest::STATUS_CANCELLED, 'responded_at' => now()]);

        return \Redirect::back()->with('tabs-school-success', 'The link request was cancelled.');
    }

    public function createFamilyMember(Request $request)
    {
        $input = $request->all();
        session()->flash('profile_hash', 'tabs-student');

        $u = new User();
        $u->forceFill([
            'firstname' => $input['firstname'],
            'lastname' => $input['lastname'],
            'school_id' => $input['school_id'],
            'rank_id' => $input['rank_id'],
            'dob' => ($input['dob']) ? new Carbon($input['dob']) : null,
            'height' => $input['height'],
            'weight' => $input['weight'],
            'sex' => $input['sex'],
            'responsible_user_id' => \Auth::user()->id,
            'is_student' => 1,
            'can_login' => 0,
        ]);
        $u->save();

        if (array_key_exists('avatar', $input)) {
            $saved = $this->processAvatar($request, $u->id);

            if (! $saved) {
                return \Redirect::back()->withErrors(['updateFamilyMember', 'Problem processing profile image.']);
            }
        }

        return \Redirect::back()->with('tabs-school-success', "'{$u->full_name}' was added successfully!");
    }
}
