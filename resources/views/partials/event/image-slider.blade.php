<div class="mb-4">
    <div id="carousel-captions" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">

            @foreach($event->getMedia("slideshow-images") as $m)
            <div class="carousel-item @if ($loop->first) active @endif">
                <img class="d-block w-full" alt="" src="{{ glideCropUrlFromMedia($m, 1200, 500) }}" />
                <div class="carousel-caption-background d-none d-md-block"></div>
                {{--<div class="carousel-caption d-none d-md-block">
                    <h3><span style="text-shadow: #000 1px 0 10px;">Slide label</span></h3>
                    <p style="text-shadow: #000 1px 0 10px;">Nulla vitae elit libero, a pharetra augue mollis interdum.</p>
                </div>--}}
            </div>
            @endforeach

        </div>
        <a class="carousel-control-prev" data-bs-target="#carousel-captions" role="button" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </a>
        <a class="carousel-control-next" data-bs-target="#carousel-captions" role="button" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </a>
    </div>
</div>
