<div class="modal" id="user{{$user->id}}Notes" tabindex="-1">

    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $user->fullname }} - Event Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- display existing notes -->
                @foreach($user->event_notes()->orderBy('created_at', 'DESC')->get() as $event_note)
                <div class="card my-2 note-card">
                    <div class="card-header container">
                        <div class="row w-100">
                            <div class="col ">
                                <h3 class="card-title">{{ $event_note->created_at->format('m/d/Y g:i a') }}</h3>
                            </div>
                            <div class="col text-end">
                                By: <span class="added-by-name">{{ $event_note->added_by_user->fullname }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary note-note">{{ $event_note->note }}</p>
                    </div>
                </div>
                @endforeach

                <!-- add note form -->
                <form>
                <div class="card mt-3">
                    <div class="card-header container bg-primary">
                        <div class="row w-100">
                            <div class="col">
                                <h3 class="card-title text-white">Create Note</h3>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <textarea class="form-control" id="new_note" name="note" placeholder="Enter note text..." rows="4"></textarea>
                        </div>
                        <div class="mb-3 text-end">
                            <button type="button" class="btn btn-primary" onclick="saveUserEventNote(this.form.note.value, {{ $user->id }});">Save</button>
                        </div>
                    </div>
                </div>
                </form>


            </div>

            <div class="modal-footer">
                <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">
                    Close
                </a>
            </div>
        </div>
    </div>
</div>

@once
    <template id="note-template">
        <div class="card my-2 note-card">
            <div class="card-header container">
                <div class="row w-100">
                    <div class="col ">
                        <h3 class="card-title"></h3>
                    </div>
                    <div class="col text-end">
                        By: <span class="added-by-name"></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p class="text-secondary note-note"></p>
            </div>
        </div>
    </template>

    @push('js')
        <script>

            function saveUserEventNote(note, userid) {
                data = {
                    'note': note
                };

                $.ajax({
                    type: "POST",
                    url: '/user/' + userid + '/add-event-note',
                    data: data,
                    success: saveUserEventNoteSuccess,
                    dataType: 'json'
                });
            }

            function saveUserEventNoteSuccess(resp) {
                console.log(resp);
                modalbody = $('#user'+resp.user_id+'Notes .modal-body').first();
                contents = $('#note-template').html();

                modalbody.prepend(contents);
                newCard = modalbody.find('div').first();
                newCard.find('h3.card-title').html(resp.formatted_date);
                newCard.find('.added-by-name').html(resp.by)
                newCard.find('.note-note').html(resp.note);
                modalbody.find('#new_note').val('');
                alert('Note added successfully!')
            }

        </script>
    @endpush
@endonce
