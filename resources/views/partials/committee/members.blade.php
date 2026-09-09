{{--
    A committee's members with their contact details.

    $user->email and $user->phone fall back to the member's guardian when they
    have none of their own, which is what you want when the person to call about
    a young member is their parent.
--}}
@forelse($members as $member)
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card card-sm h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="avatar" style="background-image: url(@if($member->avatar){{ asset('storage/avatars/'.$member->avatar) }}@else{{ '/img/default_avatar.png' }}@endif)"></span>
                    </div>
                    <div class="col text-truncate">
                        <div class="fw-bold text-truncate">{{ $member->fullname }}</div>
                        @if($member->school)
                            <div class="text-secondary text-truncate">{{ $member->school->shortname ?: $member->school->name }}</div>
                        @endif
                    </div>
                </div>
                <div class="mt-2">
                    @if($member->email)
                        <div class="text-truncate">
                            <a href="mailto:{{ $member->email }}" class="text-reset">{{ $member->email }}</a>
                        </div>
                    @endif
                    @if($member->phone)
                        <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone) }}" class="text-reset">{{ $member->phone }}</a></div>
                    @else
                        <div class="text-secondary">No phone on file</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12">
        <p class="text-secondary mb-0">No members listed yet.</p>
    </div>
@endforelse
