{{--
    Notes about one member, in the context of one event.

    $user->notes is already filtered by the controller to what belongs here —
    every permanent note, plus the temporary notes written for $event. Do not
    re-query the relation in this view or the filtering is lost.
--}}
<div class="modal" id="user{{$user->id}}Notes" tabindex="-1">

    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $user->fullname }} - Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- display existing notes -->
                @foreach($user->notes as $user_note)
                <div class="card my-2 note-card">
                    <div class="card-header container">
                        <div class="row w-100">
                            <div class="col ">
                                <h3 class="card-title">
                                    <span class="note-scope badge {{ $user_note->isPermanent() ? 'bg-red' : 'bg-yellow' }} me-2">
                                        {{ $user_note->isPermanent() ? 'Permanent' : 'This event' }}
                                    </span>
                                    {{ $user_note->created_at->format('m/d/Y g:i a') }}
                                </h3>
                            </div>
                            <div class="col text-end">
                                By: <span class="added-by-name">{{ $user_note->added_by_user->fullname }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary note-note">{{ $user_note->note }}</p>
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
                        <div class="row align-items-center">
                            <div class="col">
                                <label class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="scope{{ $user->id }}" value="temporary" checked>
                                    <span class="form-check-label">
                                        <strong>Temporary</strong>
                                        <small class="d-block text-secondary">Only for {{ $event->name }} — “needs to leave by 12:15”</small>
                                    </span>
                                </label>
                                <label class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="scope{{ $user->id }}" value="permanent">
                                    <span class="form-check-label">
                                        <strong>Permanent</strong>
                                        <small class="d-block text-secondary">Always true of them — “hard of hearing”</small>
                                    </span>
                                </label>
                            </div>
                            <div class="col-auto text-end">
                                <button type="button" class="btn btn-primary"
                                        onclick="saveUserNote(this.form, {{ $user->id }}, {{ $event->id }});">Save</button>
                            </div>
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
                        <h3 class="card-title"><span class="note-scope badge me-2"></span><span class="note-date"></span></h3>
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

            function saveUserNote(form, userid, eventid) {
                var note = form.note.value.trim();
                if (!note) {
                    alert('Enter some note text first.');
                    return;
                }

                data = {
                    'note': note,
                    'scope': form.querySelector('input[type=radio]:checked').value,
                    'event_id': eventid
                };

                $.ajax({
                    type: "POST",
                    url: '/user/' + userid + '/add-note',
                    data: data,
                    success: saveUserNoteSuccess,
                    dataType: 'json'
                });
            }

            function saveUserNoteSuccess(resp) {
                modalbody = $('#user'+resp.user_id+'Notes .modal-body').first();
                contents = $('#note-template').html();

                modalbody.prepend(contents);
                newCard = modalbody.find('div').first();
                permanent = resp.scope === 'permanent';
                newCard.find('.note-scope')
                    .addClass(permanent ? 'bg-red' : 'bg-yellow')
                    .text(permanent ? 'Permanent' : 'This event');
                newCard.find('.note-date').text(resp.formatted_date);
                newCard.find('.added-by-name').text(resp.by);
                newCard.find('.note-note').text(resp.note);
                modalbody.find('#new_note').val('');
                alert('Note added successfully!')
            }

        </script>
    @endpush
@endonce
