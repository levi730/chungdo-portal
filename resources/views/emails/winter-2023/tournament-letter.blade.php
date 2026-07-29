@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>2023 St. Louis Gateway Championship</h1>
                            <h2>
                                Tournament Venue: <br>
                                <a href="https://goo.gl/maps/pEPwHHnD9gcqDM5N8">First St Charles United Methodist Church<br>
                                801 First Capitol Dr, St. Charles MO 63301</a>
                            </h2>

                            <h3>Saturday, February 25th 2023</h3>

                            <p>Greetings Competitors,</p>

                            <p>The tournament weekend is upon us, and we are looking forward to seeing you all! Please review the information below and let us know if you have any questions!</p>

                            <p>A full set of three patches sewn onto your uniform is required for all competitors.  If you do not have patches on your uniform please see your instructor as soon as possible. Belt and uniform with patches must be worn during competition. Toenails and fingernails need to be trimmed for competition.  No jewelry or watches should be worn during competition.</p>

                            <p>Competitors will need to check in and register when they first arrive in the morning. During this time waivers will need to be filled out and turned in at registration.  If you registered prior to the t-shirt deadline you will also receive your t-shirt at this time. Once registered check the posted tournament division spreadsheet to locate your division and learn the name of your division.</p>

                            <p>Black Belts and Brown Belt volunteers please pay special attention to the day's agenda so you know when to arrive, and when your morning meeting time is scheduled.</p>

                            <p><b>Saturday, February 25th 2023</b></p>

                           <ul>

                                <li><b>7:00am</b> - Building Access for volunteers and black belts</li>
                                <li><b>7:30am</b> - Brown Belt Volunteers, Black Belts & Helpers Arrive</li>
                                <li><b>7:45am</b> - Black Belt Meeting</li>
                                <li><b>8:00am</b> - Brown Belt Meeting</li>
                                <li><b>8:30am</b> - Registration & Check-in Opens</li>
                                <li><b>9:00am</b> - Competitors should be in uniform</li>
                                <li><b>9:15am</b> - Warm-up With Master Blevins</li>
                                <li><b>9:30am</b> - Competitor Greeting & Review of Tournament Rules</li>
                                <li><b>9:30am</b> - Kids Activities</li>
                                <li><b>9:45am</b> - National Anthem, Invocation</li>
                                <li>
                                    <b>10:00am</b> - Combined School Demos (5 minutes each)
                                    <ul>
                                        <li>Lyndell & Blue Wave West</li>
                                        <li>Blue Wave Strong & Blue Wave Spirit</li>
                                        <li>Blue Wave Life & Blue Wave Legacy</li>
                                        <li>Blue Wave Balanced & Blue Wave Forever</li>
                                        <li>Master Blevins & Blue Wave Wentzville</li>
                                    </ul>
                                </li>
                                <li>
                                    <b>10:30am</b> - Competition Begins
                                    <ul>
                                        <li>
                                            Mini Pee Wee / Pee Wee Color Belts
                                            <ul>
                                                <li>Black Belts</li>
                                            </ul>
                                        </li>

                                        <li>
                                            Juniors Color Belts
                                            <ul>
                                                <li>Black Belts</li>
                                            </ul>
                                        </li>

                                        <li>
                                            Adult Color Belts
                                            <ul>
                                                <li>Black Belts</li>
                                            </ul>
                                        </li>

                                        <li>
                                            Senior Color Belts
                                            <ul>
                                                <li>Black Belts</li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                                <li><b>3:00pm</b> - Tournament Conclusion & Clean-up</li>
                                <li>
                                    <b>4:00pm</b> -  After Tournament Social Event
                                    <ul>
                                        <li>
                                            Gettemeiers Restaurant<br>
                                            269 Salt Lick Rd. St Peters MO 63376
                                        </li>
                                    </ul>
                                </li>
                            </ul>

                            <p>For your convenience, here's another copy of the QR Code used for registration check-in.  If you have saved a previous QR Code (from your initial registration email, or in your Apple Wallet), you can use either one at the door.</p>

                            <p align="center"><img src="{{ $message->embed($qr_image) }}"></p>


                            <p>If you have any questions please get in touch with your instructor or email Tammy Bauer at <a href="mailto:info@bluewavestrong.com">info@bluewavestrong.com</a></p>

                            <p>Thank you and see you all soon!</p>

                            <p>Sincerely,</p>

                            <p>The 2023 St. Louis Gateway Championship Sparring Tournament Planning Team</p>

                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
@endsection
