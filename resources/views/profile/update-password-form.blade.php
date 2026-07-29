
<form method="POST" action="{{ route('user-password.update') }}">
    @csrf
    @method('PUT')

    <h2>{{ __('Change Password') }}</h2>

    @if ($errors->updatePassword->any())
        <div class="alert alert-danger">
            <h4 class="alert-title">Error!</h4>
            <ul>
                @foreach ($errors->updatePassword->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status') == 'password-updated')
        <div class="alert alert-success">Password updated successfully!</div>
    @endif

    <div class="mb-3">
        <label class="form-label">{{ __('Current Password') }}</label>
        <input type="password" name="current_password" class="form-control" required
               autocomplete="none" />
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('New Password') }}</label>
        <input type="password" name="password" class="form-control" required
               autocomplete="none" />
    </div>

    <div class="mb-3">
        <label>{{ __('Confirm New Password') }}</label>
        <input type="password" name="password_confirmation" class="form-control" required
               autocomplete="none" />
    </div>

    <div>
        <button type="submit" class="btn btn-primary">
            {{ __('Save') }}
        </button>
    </div>
</form>
