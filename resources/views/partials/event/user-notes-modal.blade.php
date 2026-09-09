{{--
    Notes about one member, in the context of one event.

    $user->notes is already filtered by the controller to what belongs here —
    every permanent note, plus the temporary notes written for $event. Do not
    re-query the relation in this view or the filtering is lost.

    Because no other event's temporary notes are reachable from here, the event
    this modal belongs to is the only event an edit could attach a note to.
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
                <div class="card my-2 note-card" id="note-{{ $user_note->id }}">
                    <div class="card-header container">
                        <div class="row w-100 align-items-center">
                            <div class="col ">
                                <h3 class="card-title">
                                    <span class="note-scope badge {{ $user_note->isPermanent() ? 'bg-red' : 'bg-yellow' }} me-2">
                                        {{ $user_note->isPermanent() ? 'Permanent' : 'This event' }}
                                    </span>
                                    <span class="note-date">{{ $user_note->created_at->format('m/d/Y g:i a') }}</span>
                                    <small class="note-edited text-secondary" @if($user_note->updated_at <= $user_note->created_at) hidden @endif>(edited)</small>
                                </h3>
                            </div>
                            <div class="col-auto text-end">
                                By: <span class="added-by-name">{{ $user_note->added_by_user->fullname }}</span>
                            </div>
                            @can('update', $user_note)
                            <div class="col-auto text-end note-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="startEditNote({{ $user_note->id }});">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="deleteNote({{ $user_note->id }}, {{ $user->id }}, @js($user->fullname));">Delete</button>
                            </div>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary note-note note-view">{{ $user_note->note }}</p>

                        @can('update', $user_note)
                        <div class="note-edit" hidden>
                            <div class="mb-3">
                                <textarea class="form-control note-edit-text" rows="4">{{ $user_note->note }}</textarea>
                            </div>
                            <div class="row align-items-center">
                                <div class="col">
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="editscope{{ $user_note->id }}"
                                               value="temporary" @checked(! $user_note->isPermanent())>
                                        <span class="form-check-label">Temporary</span>
                                    </label>
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="editscope{{ $user_note->id }}"
                                               value="permanent" @checked($user_note->isPermanent())>
                                        <span class="form-check-label">Permanent</span>
                                    </label>
                                </div>
                                <div class="col-auto text-end">
                                    <button type="button" class="btn btn-link link-secondary"
                                            onclick="cancelEditNote({{ $user_note->id }});">Cancel</button>
                                    <button type="button" class="btn btn-primary"
                                            onclick="saveEditNote({{ $user_note->id }}, {{ $event->id }});">Save</button>
                                </div>
                            </div>
                        </div>
                        @endcan
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
    {{--
        A note created in the browser is always the current user's own, so the
        template carries the edit and delete controls unconditionally. Every
        other card gets them from @can in the loop above.
    --}}
    <template id="note-template">
        <div class="card my-2 note-card">
            <div class="card-header container">
                <div class="row w-100 align-items-center">
                    <div class="col ">
                        <h3 class="card-title">
                            <span class="note-scope badge me-2"></span>
                            <span class="note-date"></span>
                            <small class="note-edited text-secondary" hidden>(edited)</small>
                        </h3>
                    </div>
                    <div class="col-auto text-end">
                        By: <span class="added-by-name"></span>
                    </div>
                    <div class="col-auto text-end note-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary note-edit-btn">Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger note-delete-btn">Delete</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p class="text-secondary note-note note-view"></p>
                <div class="note-edit" hidden>
                    <div class="mb-3">
                        <textarea class="form-control note-edit-text" rows="4"></textarea>
                    </div>
                    <div class="row align-items-center">
                        <div class="col">
                            <label class="form-check form-check-inline">
                                <input class="form-check-input note-edit-temporary" type="radio" value="temporary">
                                <span class="form-check-label">Temporary</span>
                            </label>
                            <label class="form-check form-check-inline">
                                <input class="form-check-input note-edit-permanent" type="radio" value="permanent">
                                <span class="form-check-label">Permanent</span>
                            </label>
                        </div>
                        <div class="col-auto text-end">
                            <button type="button" class="btn btn-link link-secondary note-cancel-btn">Cancel</button>
                            <button type="button" class="btn btn-primary note-save-btn">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @push('js')
        <script>

            function noteCard(id) {
                return $('#note-' + id);
            }

            // The count and the row highlight are rendered server-side; keep them
            // honest after an add or a delete so a removed note stops showing as
            // a red 2 on the list behind the modal.
            function refreshNoteCount(userid) {
                count = $('#user' + userid + 'Notes .note-card').length;
                $('#user' + userid + 'NoteCount').text(count).toggleClass('text-danger fw-bold', count > 0);
                $('#user' + userid + 'Row').toggleClass('bg-warning-lt', count > 0);
            }

            function paintNoteScope(card, scope) {
                card.find('.note-scope')
                    .removeClass('bg-red bg-yellow')
                    .addClass(scope === 'permanent' ? 'bg-red' : 'bg-yellow')
                    .text(scope === 'permanent' ? 'Permanent' : 'This event');
            }

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
                    url: '/user/' + userid + '/notes',
                    data: data,
                    success: function (resp) { saveUserNoteSuccess(resp, eventid); },
                    dataType: 'json'
                });
            }

            function saveUserNoteSuccess(resp, eventid) {
                modalbody = $('#user'+resp.user_id+'Notes .modal-body').first();
                contents = $('#note-template').html();

                modalbody.prepend(contents);
                newCard = modalbody.find('div').first();
                newCard.attr('id', 'note-' + resp.id);
                paintNoteScope(newCard, resp.scope);
                newCard.find('.note-date').text(resp.formatted_date);
                newCard.find('.added-by-name').text(resp.by);
                newCard.find('.note-note').text(resp.note);
                newCard.find('.note-edit-text').val(resp.note);
                newCard.find('input[type=radio]').attr('name', 'editscope' + resp.id);
                newCard.find('.note-edit-' + resp.scope).prop('checked', true);
                newCard.find('.note-edit-btn').attr('onclick', 'startEditNote(' + resp.id + ');');
                newCard.find('.note-cancel-btn').attr('onclick', 'cancelEditNote(' + resp.id + ');');
                newCard.find('.note-save-btn').attr('onclick', 'saveEditNote(' + resp.id + ', ' + eventid + ');');
                newCard.find('.note-delete-btn').attr('onclick', 'deleteNote(' + resp.id + ', ' + resp.user_id + ');');

                modalbody.find('#new_note').val('');
                refreshNoteCount(resp.user_id);
                alert('Note added successfully!')
            }

            function startEditNote(id) {
                card = noteCard(id);
                card.find('.note-view').prop('hidden', true);
                card.find('.note-actions').prop('hidden', true);
                card.find('.note-edit').prop('hidden', false);
                card.find('.note-edit-text').trigger('focus');
            }

            function cancelEditNote(id) {
                card = noteCard(id);
                // Throw the draft away — the paragraph still holds what was saved.
                card.find('.note-edit-text').val(card.find('.note-view').text().trim());
                card.find('.note-edit').prop('hidden', true);
                card.find('.note-view').prop('hidden', false);
                card.find('.note-actions').prop('hidden', false);
            }

            function saveEditNote(id, eventid) {
                card = noteCard(id);
                note = card.find('.note-edit-text').val().trim();
                if (!note) {
                    alert('A note cannot be empty. Delete it instead.');
                    return;
                }

                $.ajax({
                    type: "PATCH",
                    url: '/notes/' + id,
                    data: {
                        'note': note,
                        'scope': card.find('.note-edit input[type=radio]:checked').val(),
                        'event_id': eventid
                    },
                    success: function (resp) {
                        card.find('.note-note').text(resp.note);
                        card.find('.note-edited').prop('hidden', !resp.edited);
                        paintNoteScope(card, resp.scope);
                        card.find('.note-edit').prop('hidden', true);
                        card.find('.note-view').prop('hidden', false);
                        card.find('.note-actions').prop('hidden', false);
                    },
                    dataType: 'json'
                });
            }

            function deleteNote(id, userid, who) {
                if (!confirm('Delete this note' + (who ? ' about ' + who : '') + '? This cannot be undone.')) {
                    return;
                }

                $.ajax({
                    type: "DELETE",
                    url: '/notes/' + id,
                    success: function () {
                        noteCard(id).remove();
                        refreshNoteCount(userid);
                    },
                    dataType: 'json'
                });
            }

        </script>
    @endpush
@endonce
