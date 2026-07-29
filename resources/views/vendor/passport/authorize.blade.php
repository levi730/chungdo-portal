@extends('layouts.auth')

@section('page_title', 'Authorization Request')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h1 class="h2 text-center mb-3">Authorization Request</h1>

            <p class="text-secondary text-center mb-4">
                <strong>{{ $client->name }}</strong> is requesting permission to access your account.
            </p>

            @if (count($scopes) > 0)
                <p class="mb-2"><strong>This application will be able to:</strong></p>
                <ul class="list-unstyled space-y-1 mb-4">
                    @foreach ($scopes as $scope)
                        <li class="d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler text-green me-2" width="20" height="20"
                                 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 12l5 5l10 -10"/>
                            </svg>
                            {{ $scope->description }}
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="d-flex gap-2">
                <!-- Authorize -->
                <form method="post" action="{{ route('passport.authorizations.approve') }}" class="w-50">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn btn-primary w-100">Authorize</button>
                </form>

                <!-- Cancel -->
                <form method="post" action="{{ route('passport.authorizations.deny') }}" class="w-50">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn btn-outline-secondary w-100">Cancel</button>
                </form>
            </div>
        </div>
    </div>
@endsection
