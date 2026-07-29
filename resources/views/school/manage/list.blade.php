@extends('layouts.dashboard')

@section('page-title')
    School Management
@endsection

@section('content')
    <div class="content">


        <div class="container-xl">

            <div class="row row-cards">

                @foreach($all_schools as $school)
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <span class="avatar avatar-xl avatar-rounded" style="background-image: url(./static/avatars/010m.jpg)"></span>
                            </div>
                            <div class="card-title mb-1">{{$school->name}}</div>
                            <div class="text-muted">
                                {{$school->address1}}<br>
                                @if($school->address2)
                                    {{$school->address1}}<br>
                                @endif
                                {{$school->city}}, {{$school->state}} {{$school->zip}}
                            </div>
                        </div>
                        <a href="{{ route('school.view', [$school->id]) }}" class="card-btn">View</a>

                        @if(in_array($school->id, Arr::pluck($editable_schools, 'id')))
                            <a href="{{ route('school.edit', [$school->id]) }}" class="card-btn">Edit</a>
                        @endif
                    </div>
                </div>
                @endforeach

            </div>
        </div>



        <div class="toast align-items-center text-white bg-primary border-0 mt-3 me-2 position-absolute top-0 end-0" style="z-index: 99999;" role="alert" aria-live="assertive" aria-atomic="true" id="toasterElement">
            <div class="d-flex">
                <div class="toast-body" id="toasterElementBody">

                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>


    </div>

@endsection

@push('js')
    <script>
        function submit_user_form(form_element)
        {
            var fd = new FormData();

            for(var i = 0; i < form_element.length; i++)
            {
                if(form_element[i].type == 'file') {
                    fd.append(form_element[i].name, form_element[i].files[0]);
                } else {
                    fd.append(form_element[i].name, form_element[i].value);
                }

            }


            axios.post(form_element.action, fd, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }).then((response) => {
                let mdiv = form_element.closest('.avatar-anchor');
                let avatarSpan = mdiv.querySelector('span.avatar');
                let asrc = response.data.newsrc;
                if(asrc) {
                    asrc = "url('/storage/avatars/" + response.data.newsrc + "')"
                } else {
                    asrc = "url('/img/default_avatar.png')"
                }
                avatarSpan.style.backgroundImage =  asrc;
                toaster('success', response.data.success);
            }, (error) => {
                toaster('success', response.data.errors.avatar);
            });


        }

        function toaster(type, msg) {
            toastEl = document.getElementById("toasterElement")

            toastEl.classList.remove('bg-danger');
            toastEl.classList.remove('bg-primary');
            toastEl.classList.remove('bg-success');

            toastEl.classList.add('bg-'+type);

            toastElBody = document.getElementById("toasterElementBody");
            toastElBody.innerHTML = msg;


            toast = new bootstrap.Toast(toastEl);
            toast.show();

        }
    </script>
@endpush
