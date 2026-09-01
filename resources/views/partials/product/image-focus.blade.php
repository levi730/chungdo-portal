{{-- Product images with the shared focal-point picker. Same mechanism as the
     event slideshow — the point is stored on the media's custom properties and
     drives Glide's focal crop — but the crop previews use the shapes a product
     actually appears in. --}}
@php
    $productImages = $product->getMedia('product-images')->map(fn ($m) => [
        'id' => $m->id,
        'url' => glideUrlFromMedia($m, 'w=1000'),
        'focusX' => (float) ($m->getCustomProperty('focusX') ?? 50),
        'focusY' => (float) ($m->getCustomProperty('focusY') ?? 50),
        'zoom' => (float) ($m->getCustomProperty('focusZoom') ?? 1),
    ])->values()->all();
@endphp

<div class="d-flex flex-wrap gap-3 mt-2"
     x-data="focusPicker({
        mediaBase: @js(url('/admin/products/'.$product->id.'/media')),
        csrf: @js(csrf_token()),
        images: @js($productImages),
        {{-- Only the crops that actually happen. The product page's main image
             is resized, not cropped, so a focal point does nothing there and
             previewing a hero crop would be misleading. --}}
        ratios: [
            { label: 'Store card', w: 600, h: 400 },
            { label: 'Gallery thumbnail', w: 200, h: 200 },
        ],
     })">

    <template x-for="img in images" :key="img.id">
        <div class="text-center">
            <div style="width:110px;height:110px;overflow:hidden;border-radius:4px;border:1px solid #dee2e6;">
                <img :src="img.url"
                     :style="`width:100%;height:100%;object-fit:cover;object-position:${img.focusX}% ${img.focusY}%;transform:scale(${img.zoom});transform-origin:${img.focusX}% ${img.focusY}%`">
            </div>
            <div class="d-flex justify-content-center gap-2 mt-1">
                <button type="button" class="btn btn-sm btn-link p-0" @click="edit(img)">Adjust crop</button>
                <form :action="`${mediaBase}/${img.id}`" method="POST" class="d-inline"
                      onsubmit="return confirm('Remove this image?')">
                    <input type="hidden" name="_token" :value="csrf">
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="btn btn-sm btn-link text-danger p-0">Remove</button>
                </form>
            </div>
        </div>
    </template>

    {{-- Focus-point picker modal — rendered only while open, so its current.*
         bindings never evaluate against a null `current`. --}}
    <template x-if="open">
        <div @keydown.escape.window="open = false" @click.self="open = false"
             style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1060;display:flex;align-items:center;justify-content:center;">
        <div class="card" style="width:min(940px,95vw);max-height:92vh;overflow:auto;">
            <div class="card-header">
                <h3 class="card-title mb-0">Set focus point</h3>
                <button type="button" class="btn-close ms-auto" @click="open = false"></button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7 text-center">
                        <div class="position-relative d-inline-block" style="cursor:crosshair;user-select:none;touch-action:none;max-width:100%;"
                             x-ref="canvas" @pointerdown="startDrag($event)" @pointermove="onDrag($event)"
                             @pointerup="endDrag" @pointerleave="endDrag">
                            <img :src="current.url" class="d-block" style="max-height:58vh;max-width:100%;width:auto;height:auto;border-radius:6px;" draggable="false">
                            <div style="position:absolute;width:24px;height:24px;margin:-12px 0 0 -12px;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 2px rgba(0,0,0,.65),inset 0 0 0 2px rgba(0,0,0,.35);pointer-events:none;"
                                 :style="`left:${current.focusX}%;top:${current.focusY}%`"></div>
                        </div>
                        <label class="form-label mt-3 mb-1 small">Zoom — <span x-text="current.zoom.toFixed(1) + '×'"></span></label>
                        <input type="range" class="form-range" min="1" max="3" step="0.1" x-model.number="current.zoom">
                        <div class="text-muted small">
                            Click or drag on the image to set the focal point. Previews update live.
                            This only affects the cropped shapes on the right — on the product page
                            the full image is shown uncropped.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="text-muted small mb-2">Crop previews</div>
                        <template x-for="r in ratios" :key="r.label">
                            <div class="mb-2">
                                <div class="small fw-bold" x-text="`${r.label} (${r.w}×${r.h})`"></div>
                                <div :style="`width:100%;aspect-ratio:${r.w}/${r.h};overflow:hidden;border-radius:4px;border:1px solid #dee2e6;background:#f8f9fa;`">
                                    <img :src="current.url"
                                         :style="`width:100%;height:100%;object-fit:cover;object-position:${current.focusX}% ${current.focusY}%;transform:scale(${current.zoom});transform-origin:${current.focusX}% ${current.focusY}%`">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center gap-2">
                <button type="button" class="btn btn-primary" @click="save()" :disabled="saving"
                        x-text="saving ? 'Saving…' : 'Save focus'"></button>
                <button type="button" class="btn btn-link" @click="open = false">Cancel</button>
                <button type="button" class="btn btn-outline-secondary ms-auto" @click="center()">Reset to center</button>
            </div>
        </div>
        </div>
    </template>
</div>

@include('partials.focus-picker-script')
