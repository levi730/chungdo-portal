<h2>{{ __('Active Sessions') }}</h2>

@if (session('tab-device-success'))
    <div class="alert alert-success">{{ session('tab-device-success') }}</div>
@endif

<div class="table-responsive">
    <table class="table table-vcenter datatable">
        <thead>
        <tr>
            <th>User Agent</th>
            <th>IP Address</th>
            <th>Last Activity</th>
            <th class="w-1"></th>
        </tr>
        </thead>
        <tbody>
        @foreach($devices as $device)
            <tr>
                <td>{{ $device->user_agent }}</td>
                <td>
                    {{ $device->ip_address }}
                </td>
                <td>
                    {{ Carbon\Carbon::createFromTimestamp($device->last_activity)->locale(str_replace('_', '-', app()->getLocale()))->diffForHumans() }}
                </td>
                <td>
                    @if(\Session::getId() == $device->id)
                        <button disabled="disabled" class="btn btn-primary">Current Device</button>
                    @else
                        <form action="{{ route('profile.deletedevice', ['id' => $device->id]) }}"
                              method="post">
                            @csrf
                            @method('DELETE')
                            <input type="submit" class="btn btn-danger" value="Remove" />
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
