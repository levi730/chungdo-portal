{{--
    The Refund button's confirm dialog for one registrant (event.issueRefunds).

    $summary comes from App\Services\RegistrationWithdrawal::summaries(): the
    registration's money lines, the suggested amount (this person's own lines)
    and the most that can come back (everything, including household items
    such as a donation, less anything already refunded on the same payment).
    Submitting withdraws the registration whatever the amount — $0 is "keep the
    money, remove the person".
--}}
@php($regId = $user->pivot->id)
<div class="modal" id="withdraw{{ $regId }}" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('event.registrant-withdraw', [$event->slug, $regId]) }}" class="modal-content"
              onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Refund &amp; withdraw {{ $user->full_name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($summary['awaiting_cash'])
                    <div class="alert alert-warning mb-0">
                        This registration is waiting on a cash/check envelope through their instructor.
                        Void the remittance instead, or wait until it has been received.
                    </div>
                @else
                    @if(count($summary['lines']))
                        <table class="table table-sm mb-3">
                            <thead><tr><th>Paid for</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                @foreach($summary['lines'] as $line)
                                    <tr>
                                        <td>
                                            {{ $line['label'] }}
                                            @if($line['estimated'])
                                                <span class="badge bg-yellow-lt text-yellow ms-1" title="This registration predates itemized fees; the fee is the payment's remainder split evenly across its registrants.">estimated</span>
                                            @endif
                                            @if($line['group'])
                                                <span class="badge bg-azure-lt text-azure ms-1" title="A household item, not this person's own. Not included in the suggested amount.">household</span>
                                            @endif
                                        </td>
                                        <td class="text-end">${{ number_format($line['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">Nothing was paid for this registration.</p>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Refund amount</label>
                        <div class="input-group" style="max-width: 12rem;">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" max="{{ $summary['max'] }}" name="amount"
                                   value="{{ number_format($summary['suggested'], 2, '.', '') }}" class="form-control" required>
                        </div>
                        <small class="form-hint">
                            Up to ${{ number_format($summary['max'], 2) }} can be returned. Lower it to keep a processing fee.
                            @if(in_array('stripe', $summary['methods']))
                                Refunded to the card that paid, on the event's Stripe account.
                            @endif
                            @if(count(array_diff($summary['methods'], ['stripe', 'none'])))
                                Paid by cash or check: recorded here, but the money has to be handed back in person.
                            @endif
                        </small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Note <span class="text-muted">(optional, kept with the refund)</span></label>
                        <input type="text" name="note" class="form-control" maxlength="500" placeholder="e.g. Injured, cannot compete">
                    </div>

                    <div class="text-muted small">
                        {{ $user->full_name }} is removed from the event, their division and the check-in list.
                        They can register again if they change their mind.
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
                @unless($summary['awaiting_cash'])
                    <button type="submit" class="btn btn-danger">Refund &amp; withdraw</button>
                @endunless
            </div>
        </form>
    </div>
</div>
