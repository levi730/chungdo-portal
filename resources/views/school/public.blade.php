@extends('layouts.dashboard')

@section('page-title')
    Our Schools
@endsection

@section('content')
<div class="container-xl">
    <div class="content">

        <p class="text-secondary mb-4" style="max-width:44rem;">
            Chung Do Association schools. Get in touch with whichever is nearest to you —
            they'll be glad to hear from you.
        </p>

        <div class="row row-cards">
            @forelse($schools as $school)
                <div class="col-md-6 col-xl-4">
                    {{-- manageable defaults to false: no View, Edit or Restore
                         controls here, and archived schools never reach this
                         list in the first place. --}}
                    @include('school.partials.card', ['school' => $school])
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-secondary">No schools listed yet.</div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
