{{--
    Product gallery: a main image, a clickable thumbnail strip, and a lightbox.

    The main image is RESIZED, not cropped. A hero exists to show the whole
    design, and cropping to a fixed ratio chops a portrait photo top and bottom —
    the focal point keeps the subject in frame but can't stop the chopping. Only
    the thumbnails crop, because they have to be uniform squares to form a strip,
    and that is where the stored focal point earns its keep.

    max-height keeps a very tall photo from pushing the buy button off-screen;
    object-fit:contain means nothing is lost, the image just sits within it.
--}}
@php($galleryImages = $images->map(fn ($m) => [
    'id' => $m->id,
    'thumb' => glideCropUrlFromMedia($m, 200, 200),
    'main' => glideUrlFromMedia($m, 'w=1200'),
    'full' => glideUrlFromMedia($m, 'w=2000'),
])->values())

<div x-data="{
        images: @js($galleryImages),
        current: 0,
        open: false,
        next() { this.current = (this.current + 1) % this.images.length; },
        prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
     }"
     @keydown.escape.window="open = false"
     @keydown.arrow-right.window="if (open) next()"
     @keydown.arrow-left.window="if (open) prev()">

    <img :src="images[current].main"
         alt="{{ $product->name }}"
         class="w-100 rounded"
         style="max-height:60vh;object-fit:contain;background:#f8f9fa;cursor:zoom-in;"
         @click="open = true">

    <template x-if="images.length > 1">
        <div class="d-flex flex-wrap gap-2 mt-2">
            <template x-for="(image, index) in images" :key="image.id">
                <img :src="image.thumb"
                     alt=""
                     class="rounded"
                     style="width:78px;height:78px;object-fit:cover;cursor:pointer;"
                     :style="index === current
                        ? 'width:78px;height:78px;object-fit:cover;cursor:pointer;outline:2px solid var(--tblr-primary);outline-offset:1px;'
                        : 'width:78px;height:78px;object-fit:cover;cursor:pointer;opacity:.65;'"
                     @click="current = index"
                     @mouseenter="$el.style.opacity = 1"
                     @mouseleave="if (index !== current) $el.style.opacity = .65">
            </template>
        </div>
    </template>

    <div class="text-muted small mt-1" x-show="images.length > 1">
        Click an image to see it larger.
    </div>

    {{-- Lightbox. Rendered only while open so its bindings never evaluate
         against a torn-down state, same shape as the admin focus picker. --}}
    <template x-if="open">
        <div @click.self="open = false"
             style="position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1060;display:flex;align-items:center;justify-content:center;">

            <button type="button" class="btn btn-icon btn-ghost-light"
                    style="position:absolute;top:1rem;right:1rem;color:#fff;font-size:1.5rem;line-height:1;"
                    @click="open = false" aria-label="Close">&times;</button>

            <template x-if="images.length > 1">
                <button type="button" class="btn btn-icon btn-ghost-light"
                        style="position:absolute;left:1rem;color:#fff;font-size:2rem;line-height:1;"
                        @click.stop="prev()" aria-label="Previous">&lsaquo;</button>
            </template>

            <img :src="images[current].full" alt="{{ $product->name }}"
                 style="max-width:92vw;max-height:88vh;object-fit:contain;border-radius:6px;">

            <template x-if="images.length > 1">
                <button type="button" class="btn btn-icon btn-ghost-light"
                        style="position:absolute;right:1rem;color:#fff;font-size:2rem;line-height:1;"
                        @click.stop="next()" aria-label="Next">&rsaquo;</button>
            </template>

            <div x-show="images.length > 1"
                 style="position:absolute;bottom:1rem;color:#fff;font-size:.875rem;">
                <span x-text="current + 1"></span> / <span x-text="images.length"></span>
            </div>
        </div>
    </template>
</div>
