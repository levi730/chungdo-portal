@canany(['event.viewAllSchoolRegistrants', 'event.checkIn', 'event.recordResults'])

    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('event.register', $event->slug) }}">Main</a>
                    </li>
                    @can('event.viewAllSchoolRegistrants')
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            View Registrants
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="{{ route('event.registrants', $event->slug) }}">By School</a></li>
                            @if(Str::contains(strtolower($event->slug), 'tournament'))
                            <!--    <li><a class="dropdown-item" href="{{ route('event.registrants-by-nat-div', $event->slug) }}">By Natural Division</a></li>
                                <li><a class="dropdown-item" href="{{ route('event.registrants-by-div', $event->slug) }}">By Assigned Division</a></li> -->
                            @endif
                            @if($event->hasAddon('volunteer'))
                                <li><a class="dropdown-item" href="{{ route('event.registrants-by-volunteer', $event->slug) }}">By Volunteer Selection</a></li>
                            @endif
                            @if($event->hasAddon('potluck'))
                                <li><a class="dropdown-item" href="{{ route('event.registrants-by-potluck', $event->slug) }}">By Potluck Item</a></li>
                            @endif
                            @foreach($event->enabledAddons() as $addon)
                                @if($addon->handler() && $addon->handler()->reportView())
                                    <li><a class="dropdown-item" href="{{ route('event.registrants-by-addon', [$event->slug, $addon->type]) }}">By {{ $addon->label() }}</a></li>
                                @endif
                            @endforeach
                            <li><a class="dropdown-item" href="{{ route('event.reg-spreadsheet', $event->slug) }}">Download Registrant Spreadsheet</a></li>
                        </ul>
                    </li>
                    @endcan
                    @php($pendingRefunds = auth()->user()?->can('event.approveRefunds')
                        ? \App\Models\AddonChangeRequest::where('event_id', $event->id)->where('status', 'pending')->count()
                        : 0)
                    @canany(['event.viewAllSchoolRegistrants', 'event.approveRefunds'])
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="moneyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Money
                                @if($pendingRefunds > 0)
                                    <span class="badge bg-red text-white ms-1">{{ $pendingRefunds }}</span>
                                @endif
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="moneyDropdown">
                                @can('event.viewAllSchoolRegistrants')
                                    <li><h6 class="dropdown-header">Cash/Check</h6></li>
                                    @can('create', [\App\Models\CashRemittance::class, $event])
                                        <li><a class="dropdown-item" href="{{ route('cash.register', $event->slug) }}">Register students paying cash/check</a></li>
                                    @endcan
                                    <li><a class="dropdown-item" href="{{ route('cash.remittances', ['event' => $event->id, 'status' => 'all']) }}">Remittances for this event</a></li>
                                @endcan
                                @can('event.approveRefunds')
                                    @can('event.viewAllSchoolRegistrants')
                                        <li><hr class="dropdown-divider"></li>
                                    @endcan
                                    <li>
                                        <a class="dropdown-item" href="{{ route('event.refund-requests', $event->slug) }}">
                                            Refund Requests
                                            @if($pendingRefunds > 0)
                                                <span class="badge bg-red text-white ms-1">{{ $pendingRefunds }}</span>
                                            @endif
                                        </a>
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('event.financials-spreadsheet', $event->slug) }}">Download Financials</a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcanany
                    @canany(['event.viewAllSchoolRegistrants', 'event.checkIn', 'event.reorganizeDivisions'])
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="organizationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Organization
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="organizationDropdown">
                                @can('event.viewAllSchoolRegistrants')
                                    <li><h6 class="dropdown-header">Registration Cards</h6></li>
                                @if($event->isCompetition())
                                    @if($event->hasForms())
                                        <li><a class="dropdown-item" href="{{ route('event.print-tournament-cards', $event->slug) }}?variant=forms" target="_blank">Print Forms Cards</a></li>
                                    @endif
                                    @if($event->hasSparring())
                                        <li><a class="dropdown-item" href="{{ route('event.print-tournament-cards', $event->slug) }}?variant=sparring" target="_blank">Print Sparring Cards</a></li>
                                    @endif
                                    @if($event->hasForms() && $event->hasSparring())
                                        <li><a class="dropdown-item" href="{{ route('event.print-tournament-cards', $event->slug) }}?variant=both" target="_blank">Print Both Cards</a></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    @if($event->hasForms())
                                        <li><a class="dropdown-item" href="{{ route('event.print-tournament-cards', $event->slug) }}?variant=forms&blanks=1" target="_blank">Print Blank Forms Card</a></li>
                                    @endif
                                    @if($event->hasSparring())
                                        <li><a class="dropdown-item" href="{{ route('event.print-tournament-cards', $event->slug) }}?variant=sparring&blanks=1" target="_blank">Print Blank Sparring Card</a></li>
                                    @endif
                                @else
                                    <li><a class="dropdown-item" href="{{ route('event.print-regs', $event->slug) }}" target="_blank">Print Registration Cards</a></li>
                                    <li><a class="dropdown-item" href="{{ route('event.print-regs', $event->slug) }}?blanks=1" target="_blank">Print Blank Registration Card</a></li>
                                @endif

                                    <li><hr class="dropdown-divider"></li>
                                @endcan
                                @can('event.checkIn')
                                    <li><a class="dropdown-item" href="{{ route('event.check-in', $event->slug) }}">Registrant Check-In</a></li>
                                @endcan
                                @can('event.reorganizeDivisions')
                                    <li><a class="dropdown-item" href="{{ route('event.arrange-divisions', $event->slug) }}">Arrange Divisions</a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcanany
                    @can('event.recordResults')
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="resultsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Results
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="resultsDropdown">
                                <li><a class="dropdown-item" href="{{ route('event.results', $event->slug) }}">Results Page</a></li>
                                <li><a class="dropdown-item" href="{{ route('event.scoring', $event->slug) }}">Scoring</a></li>
                                <li><a class="dropdown-item" href="{{ route('event.trophies', $event->slug) }}">Participation Trophies</a></li>
                                <li><a class="dropdown-item" href="{{ route('event.awards', $event->slug) }}">Awards Sheet</a></li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('event.results', $event->slug) }}">Results</a>
                        </li>
                    @endcan
                    @can('event.manage')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('events.edit', $event) }}">Manage Event</a>
                        </li>
                    @elsecan('event.manageAddons')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('event.addons', $event->slug) }}">Manage Add-ons</a>
                        </li>
                    @endcan
                </ul>
            </div>
        </div>
    </nav>


@endcanany
