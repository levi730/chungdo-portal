
<div class="row row-cards" x-data="{ foo: 'bar' }">
    <div class="col-12">
        <div class="btn-list float-end">
            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-link-account">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 15l6 -6" /><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" /><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" /></svg>
                Link an existing account
            </a>
            <a href="#" class="btn btn-primary d-none d-sm-inline-block" data-bs-toggle="modal" data-bs-target="#modal-new-student">
                <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                Add student
            </a>
            <a href="#" class="btn btn-primary d-sm-none btn-icon" data-bs-toggle="modal" data-bs-target="#modal-new-student" aria-label="Add student">
                <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
            </a>
        </div>
    </div>


    @if ($errors->updateFamilyMember->any())
        <div class="alert alert-danger">
            <h4 class="alert-title">Error!</h4>
            <ul>
                @foreach ($errors->updateFamilyMember->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('tabs-school-success'))
        <div class="alert alert-success">{{ session('tabs-school-success') }}</div>
    @endif

    @php
        $incomingLinks = auth()->user()->pendingLinkRequestsReceived()->get();
        $outgoingLinks = auth()->user()->linkRequestsSent()->where('status', 'pending')->with('recipient')->get();
    @endphp

    @foreach($incomingLinks as $link)
        <div class="alert alert-info d-flex align-items-center justify-content-between">
            <div>
                <strong>{{ $link->requester->full_name }}</strong> wants to link their family account with yours.
                Once you accept, you'll each be able to register and pay for the other's family.
            </div>
            <div class="btn-list flex-nowrap ms-3">
                <form method="POST" action="{{ route('account-link.accept', ['id'=>$link->id]) }}">
                    @csrf
                    <button class="btn btn-success">Accept</button>
                </form>
                <form method="POST" action="{{ route('account-link.decline', ['id'=>$link->id]) }}">
                    @csrf
                    <button class="btn btn-outline-secondary">Decline</button>
                </form>
            </div>
        </div>
    @endforeach

    @foreach($outgoingLinks as $link)
        <div class="alert alert-secondary d-flex align-items-center justify-content-between">
            <div>Link request to <strong>{{ $link->recipient->full_name }}</strong> is pending their acceptance.</div>
            <form method="POST" action="{{ route('account-link.cancel', ['id'=>$link->id]) }}" class="ms-3">
                @csrf
                <button class="btn btn-outline-secondary">Cancel</button>
            </form>
        </div>
    @endforeach

    @foreach(auth()->user()->dependents as $fam)
        @push('js')
            <script>
                document.addEventListener('DOMContentLoaded', function() {

                    var myModalEl{{ $fam->id }} = document.getElementById('modal-edit-{{ $fam->id }}');
                    myModalEl{{ $fam->id }}.addEventListener('show.bs.modal', function (event) {
                        var frm = document.getElementById('edit_form_{{ $fam->id }}');
                        in2fi(frm);
                    })
                });
            </script>
        @endpush
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <form method="POST" id="del_form_{{ $fam->id }}" name="del_form_{{ $fam->id }}" action="{{ route('profile-family.delete', ['id'=>$fam->id]) }}">
                @csrf
                @method('delete')
            <div class="card-body p-4 text-center">

                <span class="avatar avatar-xl mb-3 avatar-rounded" style="background-image: url(@if ($fam->avatar) {{ asset('storage/avatars/'.$fam->avatar) }} @else '/img/default_avatar.png' @endif)"></span>
                <h3 class="m-0 mb-1"><a href="#">{{ $fam->full_name }}</a></h3>
                {{--<div class="text-muted">UI Designer</div>--}}
                <div class="mt-3">
                    <span class="badge" style="@if(strtoupper($fam->rank->color) == 'FFFFFF')border: thin solid black; @endif background-color: #{{ $fam->rank->color }}; color: #{{$fam->rank->content_color}}">{{ $fam->rank->rank }}</span>
                </div>
            </div>
            <div class="d-flex">
                <a href="#" class="card-btn" data-bs-toggle="modal" data-bs-target="#modal-edit-{{ $fam->id }}" aria-label="Edit {{$fam->full_name}}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-edit" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 7h-3a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-3"></path><path d="M9 15h3l8.5 -8.5a1.5 1.5 0 0 0 -3 -3l-8.5 8.5v3"></path><line x1="16" y1="5" x2="19" y2="8"></line></svg>
                    Edit</a>

                <a href="#" onclick="if(confirm('Are you sure?')) { document.getElementById('del_form_{{$fam->id}}').submit(); } return false;" class="card-btn text-danger"><!-- Download SVG icon from http://tabler-icons.io/i/phone -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><line x1="4" y1="7" x2="20" y2="7"></line><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path></svg>
                    Delete</a>

                @if($fam->can_login)
                    <span class="card-btn text-muted" title="This person has their own login">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-user-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path><path d="M6 21v-2a4 4 0 0 1 4 -4h4"></path><path d="M15 19l2 2l4 -4"></path></svg>
                        Can log in</span>
                @else
                    <a href="#" class="card-btn text-primary" data-bs-toggle="modal" data-bs-target="#modal-invite-{{ $fam->id }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-mail-forward" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 18h-7a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v6.5"></path><path d="M3 6l9 6l9 -6"></path><path d="M15 18h6"></path><path d="M18 15l3 3l-3 3"></path></svg>
                        Invite to log in</a>
                @endif
            </div>
            </form>
        </div>

        <div class="modal modal-blur fade modal-edit" id="modal-edit-{{ $fam->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">{{$fam->fullname}}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body avatar-anchor">

                        <div class="row mb-3">
                            <div class="col-sm-3 text-center m-auto">
                            <span
                                style="--tblr-avatar-size: 8rem; background-image: url(@if ($fam->avatar) {{ asset('storage/avatars/'.$fam->avatar) }} @else '/img/default_avatar.png' @endif)"
                                class="avatar avatar-xl"></span>
                            </div>
                            <div class="col-sm-9">
                                <form enctype="multipart/form-data" action="{{ route('profile.avatar', ['id'=>$fam->id]) }}"
                                      method="POST" id="updateAvatarForm{{$fam->id}}" onsubmit="submit_user_form(this); return false;">
                                    @csrf
                                    <div class="form-group mb-3">
                                        <div class="form-label">Upload your own Profile Picture</div>
                                        <input type="file" name="avatar" class="form-control"
                                               accept=".png,.jpg,.jpeg,.gif,.webp">
                                        <small class="form-hint">We recommend uploading a image with a min width of
                                            200px and a max width of 1000px. Only png, jpg, gif and webp files are
                                            allowed.</small>
                                    </div>
                                    <input type="submit" class="btn btn-primary">
                                    <button class="btn btn-danger"
                                            onclick="event.preventDefault(); submit_user_form(document.getElementById('deleteAvatarForm{{$fam->id}}')); return false;">Delete Profile
                                        Picture</button>

                                </form>
                            </div>
                            <form action="{{ route('profile.deleteavatar', ['id'=>$fam->id]) }}" method="post" id="deleteAvatarForm{{$fam->id}}" onSubmit="submit_user_form(this); return false;">
                                @csrf
                                @method('DELETE')
                            </form>

                        </div>

                        <hr>

                        <form method="POST" id="edit_form_{{ $fam->id }}" name="edit_form_{{ $fam->id }}" action="{{ route('profile-family.update', ['id'=>$fam->id]) }}" autocomplete="off">
                            @csrf
                            <div class="row">
                                <div class="col-sm mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="firstname" class="form-control"
                                           value="{{ old('firstname') ?? $fam->firstname }}" required autofocus
                                           autocomplete="none" />
                                </div>
                                <div class="col-sm mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="lastname" class="form-control"
                                           value="{{ old('lastname') ?? $fam->lastname }}" required
                                           autocomplete="none" />
                                </div>
                            </div>
                            <x-student-edit-form :user="$fam" />
                        </form>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">
                            Cancel
                        </a>
                        <button class="btn btn-primary ms-auto" onclick="document.getElementById('edit_form_{{ $fam->id }}').submit();">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>
                            Save
                        </button>
                    </div>

                </div>

            </div>
        </div>

        @unless($fam->can_login)
        <div class="modal modal-blur fade" id="modal-invite-{{ $fam->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <form method="POST" action="{{ route('profile-family.invite', ['id'=>$fam->id]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Invite {{ $fam->firstname }} to log in</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">
                                We'll email a link so {{ $fam->firstname }} can set a password and sign in.
                                They'll be able to register and pay for everyone in your family.
                            </p>
                            <div class="mb-2">
                                <label class="form-label">Email address</label>
                                <input type="email" name="email" class="form-control"
                                       value="{{ old('email') ?? $fam->getRawOriginal('email') }}" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</a>
                            <button class="btn btn-primary ms-auto">Send invitation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endunless
    </div>
    @endforeach

    <div class="modal modal-blur fade" id="modal-new-student" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" id="add_form" name="add_form" action="{{ route('profile-family.create') }}" enctype="multipart/form-data">
                    @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-control bg-white"
                                   value="{{ old('firstname') ?? null }}" required autofocus
                                   autocomplete="none" />
                        </div>
                        <div class="col-sm mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-control bg-white"
                                   value="{{ old('lastname') ?? null }}" required
                                   autocomplete="none" />
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group mb-3">
                            <div class="form-label">Upload your own Profile Picture</div>
                            <input type="file" name="avatar" class="form-control"
                                   accept=".png,.jpg,.jpeg,.gif,.webp">
                            <small class="form-hint">We recommend uploading a image with a min width of
                                200px and a max width of 1000px. Only png, jpg, gif and webp files are
                                allowed.</small>
                        </div>
                    </div>

                    @php
                    $newuser = new \App\Models\User();
                    $newuser->school_id = auth()->user()->school_id;
                    @endphp

                    <x-student-edit-form :user="$newuser" />

                </div>

                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">
                        Cancel
                    </a>
                    <button href="#" class="btn btn-primary ms-auto">
                        <!-- Download SVG icon from http://tabler-icons.io/i/plus -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                        Save
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="modal-link-account" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('account-link.request') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Link an existing account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">
                            Enter the email of another member who already has an account (for example,
                            your spouse). We'll send them a request, and once they accept, you'll each
                            be able to register and pay for the other's family. Neither account is changed
                            or merged.
                        </p>
                        <div class="mb-2">
                            <label class="form-label">Their email address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</a>
                        <button class="btn btn-primary ms-auto">Send request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



</div>

@push('js')
    <script>

    </script>
@endpush
