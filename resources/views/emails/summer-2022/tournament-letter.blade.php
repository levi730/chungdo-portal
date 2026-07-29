@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>Summer 2022
                                <br>Sparring Tournament</h1>
                            <h2>Saturday, August 6th 2022</h2>

                            <h4>Schedule of Days Events</h4>
                            <ul>
                                <li><b>8:00am-9:30am:</b> Set-up</li>
                                <li><b>9:30am:</b> Registration Table opens</li>
                                <li><b>10:00am-10:45am:</b> Kids Activities & board breaking</li>
                                <li><b>10:45am-11:00am:</b> All students line up
                                    <ul>
                                        <li>Rules explained, national anthem, membership oath, warm up with Master Blevins</li>
                                    </ul>
                                </li>
                                <li>
                                    <b>11:15-11:30am (Aprx):</b> Preliminary competition begins in the following order:
                                    <ul>
                                        <li>Mini Pee Wee (5-8) & Pee Wee (9-11) competition - lowest to highest rank order</li>
                                        <li>Junior (12-15) competition - lowest to highest rank order</li>
                                        <li>Adult (16-39) & Executive (40+) competition - lowest to highest rank order</li>
                                    </ul>
                                </li>
                                <li><b>When all prelim matches are complete:</b> Dismiss for lunch and fellowship (Community Center to stay open during this time for out of town participants)</li>
                                <li><b>5:30pm:</b> RECONVENE for Finals matches and demos</li>
                                <li><b>6:30pm (aprx):</b> Social event with ice cream & cake!</li>
                            </ul>

                            <br><br>
                            <h2>TOURNAMENT VENUE LOCATION & ADDITIONAL INFORMATION</h2>
                            <ul>
                                <li>The event Venue Will be the Hamel Community Center Located at at 10 Park Ave Hamel, IL 62046 (<a href="https://goo.gl/maps/Yq851tpM8jYWdZX29">Map/Directions</a>). It is off the main road (HWY 140) through Hamel
                                <li>Ammenities available include: playground, tennis courts, sand volleyball court, basketball court, outdoor picnic pavilions and a large grass field, as well as indoor & outdoor bathrooms</li>
                                <li>There will be a warm-up and gear/bag area</li>
                                <li>the community center will stay open and accessible during our break between prelims and finals. Feel free to use the amenities during our tournament downtime! Bring some balls!</li>
                            </ul>

                            <h4>RESTAURANTS & SOCIAL SPOTS WALKING DISTANCE & NEARBY</h4>

                            <ul>
                                <li><b>DK’s Market:</b> Located across the street from the community center. Grocery store with a deli with hot meals, and a case with prepared sandwiches and salads. Very small amount of indoor seating</li>
                                <li><b>Route 66 Creamery:</b> Located just a couple blocks from the community center. They have ice cream, hotdogs, brats, hamburgers, and more! This is an outdoor dining venue</li>
                                <li><b>Weezy’s Bar & Grill:</b> Located just a couple blocks from the community center. They have an assortment of food with indoor and outdoor dining</li>
                                <li><b>Subway/McDonalds:</b> Isn’t quite walking distance but is a short drive just off the interstate located at the truck stop</li>
                                <li><b>Edwardsville, IL Eating:</b> Located just 15 minutes from the community center. Edwardsville is known for being a dining and shopping hotspot! The Main Street area is full of great restaurants and shops! Just ask any of the Blue Wave Edwardsville students for suggestions, you will get many to pick from!</li>
                            </ul>

                            <br><br>
                            <h2>REMINDERS TO COMPETITORS:</h2>
                            <ul>
                                <li>Fingers and toenails should be trimmed</li>
                                <li>Competitors must have a set of three patches on their uniform</li>
                                <li>Must be wearing their belt</li>
                                <li>No jewelry is allowed during competition</li>
                                <li>Bring your confirmation email (on device, or printed) with the QR code to speed up the check in process</li>
                                <li>Bring your signed waiver <b><i>(attached)</i></b> to speed up the check in process</li>
                                <li>Bring your passport for special stamp opportunities</li>
                                <li>Have fun and learn a lot!</li>
                            </ul>

                            <br><br>
                            <p>
                                Thanks, and see you at the tournament!<br>
                                {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
@endsection
