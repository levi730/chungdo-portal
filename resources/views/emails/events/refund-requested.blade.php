<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #222;">
    <h2>Refund request needs approval</h2>

    <p><strong>{{ $studentName }}</strong> requested an add-on change on
        <strong>{{ $eventName }}</strong> that would refund
        <strong>${{ number_format($amount, 2) }}</strong>.</p>

    @if(count($lines))
        <table cellpadding="6" style="border-collapse: collapse; margin: 12px 0;">
            <thead>
                <tr>
                    <th align="left" style="border-bottom: 1px solid #ccc;">Add-on</th>
                    <th align="left" style="border-bottom: 1px solid #ccc;">Change</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td>{{ $line['fromText'] }} &rarr; <strong>{{ $line['toText'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>Nothing has changed on the registration yet — approving issues the Stripe
        refund and applies the change; denying leaves it as-is.</p>

    <p>
        <a href="{{ $reviewUrl }}" style="background: #206bc4; color: #fff; padding: 10px 16px; border-radius: 4px; text-decoration: none;">
            Review refund requests
        </a>
    </p>
</body>
</html>
