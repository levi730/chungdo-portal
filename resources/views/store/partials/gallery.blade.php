{{--
    Product gallery: a main image, a clickable thumbnail strip, and a lightbox.

    The main image is RESIZED, not cropped. A hero exists to show the whole
    design, and cropping to a fixed ratio chops a portrait photo top and bottom —
    the focal point keeps the subject in frame but can't stop the chopping. Only
    the thumbnails crop, because they have to be uniform squares to form a strip,
    and that is where the stored focal point earns its keep.

    max-height keeps a very tall photo from pushing the buy button off-screen;
    object-fit:contain means nothing is lost, the image just sits within it.

    A photo may be tagged in the admin with the item it shows (Youth Hoodie,
    Adult Hoodie...). The tag is printed on the photo, and the gallery and the
    variant picker keep each other in step through two window events:
    `gear-item` (the picker chose an item: show its photo, dim the others) and
    `gear-photo` (a tagged photo was tapped: choose that item). An untagged
    photo shows every item and is never dimmed.
--}}
@php($galleryImages = $images->map(fn ($m) => [
    'id' => $m->id,
    'thumb' => glideCropUrlFromMedia($m, 200, 200),
    'main' => glideUrlFromMedia($m, 'w=1200'),
    'full' => glideUrlFromMedia($m, 'w=2000'),
    'item' => $m->getCustomProperty('item'),
])->values())

<div x-data="{
        images: @js($galleryImages),
        current: 0,
        open: false,
        item: null,
        next() { this.current = (this.current + 1) % this.images.length; },
        prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },

        // The picker chose an item. Jump to its first photo unless the one on
        // screen already shows it (or shows everything).
        showItem(item) {
            this.item = item || null;
            if (!this.item || this.matches(this.images[this.current])) return;
            const index = this.images.findIndex(i => i.item === this.item);
            if (index >= 0) this.current = index;
        },
        matches(image) { return !image.item || !this.item || image.item === this.item; },
        dimmed(image) { return !this.matches(image); },
        select(index) {
            this.current = index;
            const item = this.images[index].item;
            if (item && item !== this.item) this.$dispatch('gear-photo', item);
        },
     }"
     @gear-item.window="showItem($event.detail)"
     @keydown.escape.window="open = false"
     @keydown.arrow-right.window="if (open) next()"
     @keydown.arrow-left.window="if (open) prev()">

    <div class="position-relative">
        <img :src="images[current].main"
             :alt="images[current].item ? '{{ $product->name }} — ' + images[current].item : '{{ $product->name }}'"
             class="w-100 rounded"
             style="max-height:60vh;object-fit:contain;background:#f8f9fa;cursor:zoom-in;"
             @click="open = true">
        <span x-show="images[current].item" x-text="images[current].item"
              class="badge bg-dark text-white"
              style="position:absolute;left:.75rem;bottom:.75rem;font-size:.875rem;opacity:.9;pointer-events:none;"></span>
    </div>

    <template x-if="images.length > 1">
        <div class="d-flex flex-wrap gap-2 mt-2">
            <template x-for="(image, index) in images" :key="image.id">
                <button type="button" class="p-0 border-0 bg-transparent text-start"
                        style="width:78px;"
                        :title="image.item || ''"
                        :aria-label="image.item ? 'Show ' + image.item : 'Show photo ' + (index + 1)"
                        @click="select(index)">
                    <img :src="image.thumb"
                         alt=""
                         class="rounded d-block"
                         style="width:78px;height:78px;object-fit:cover;transition:opacity .15s,filter .15s;"
                         :style="index === current
                            ? 'outline:2px solid var(--tblr-primary);outline-offset:1px;'
                            : (dimmed(image) ? 'opacity:.3;filter:grayscale(1);' : 'opacity:.65;')">
                    <div class="small text-truncate lh-sm mt-1"
                         :class="dimmed(image) && index !== current ? 'text-muted' : ''"
                         x-text="image.item || ''"
                         style="font-size:.75rem;min-height:1.1em;"></div>
                </button>
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

            <div x-show="images[current].item" x-text="images[current].item"
                 style="position:absolute;top:1rem;left:1rem;color:#fff;font-size:1rem;background:rgba(0,0,0,.5);padding:.25rem .6rem;border-radius:4px;"></div>

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
