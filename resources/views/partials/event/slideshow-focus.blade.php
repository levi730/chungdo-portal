{{-- Slideshow images with a focal-point picker. The focus point (0-100%) + zoom
     are saved to each media's custom properties and drive Glide's focal crop
     (see glideCropUrlFromMedia), so one point yields correct crops at any size. --}}
@php
    $slideImages = $event->getMedia('slideshow-images')->map(fn ($m) => [
        'id' => $m->id,
        'url' => glideUrlFromMedia($m, 'w=1000'),
        'focusX' => (float) ($m->getCustomProperty('focusX') ?? 50),
        'focusY' => (float) ($m->getCustomProperty('focusY') ?? 50),
        'zoom' => (float) ($m->getCustomProperty('focusZoom') ?? 1),
    ])->values()->all();
@endphp

<div class="d-flex flex-wrap gap-3 mt-2"
     x-data="focusPicker({
        mediaBase: @js(url('/admin/events/'.$event->id.'/media')),
        csrf: @js(csrf_token()),
        images: @js($slideImages),
     })">

    <template x-for="img in images" :key="img.id">
        <div class="text-center">
            <div style="width:110px;height:66px;overflow:hidden;border-radius:4px;border:1px solid #dee2e6;">
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
                        <div class="text-muted small">Click or drag on the image to set the focal point. Previews update live.</div>
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

@once
@push('js')
<script>
    function focusPicker(config) {
        return {
            images: config.images,
            mediaBase: config.mediaBase,
            csrf: config.csrf,
            open: false,
            saving: false,
            dragging: false,
            current: null,   // an editable copy of the image being adjusted
            _editing: null,  // the entry in images[] it maps back to
            ratios: [
                { label: 'Hero', w: 1200, h: 500 },
                { label: 'Square card', w: 400, h: 400 },
                { label: 'Wide banner', w: 1500, h: 400 },
            ],

            edit(img) {
                this._editing = img;
                this.current = { ...img };
                this.open = true;
            },
            center() {
                this.current.focusX = 50;
                this.current.focusY = 50;
                this.current.zoom = 1;
            },

            startDrag(e) { this.dragging = true; this.setPoint(e); },
            onDrag(e) { if (this.dragging) this.setPoint(e); },
            endDrag() { this.dragging = false; },
            setPoint(e) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                const clamp = (v) => Math.min(100, Math.max(0, Math.round(v * 100) / 100));
                this.current.focusX = clamp(((e.clientX - rect.left) / rect.width) * 100);
                this.current.focusY = clamp(((e.clientY - rect.top) / rect.height) * 100);
            },

            async save() {
                this.saving = true;
                try {
                    const res = await fetch(`${this.mediaBase}/${this.current.id}/focus`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ focusX: this.current.focusX, focusY: this.current.focusY, zoom: this.current.zoom }),
                    });
                    if (res.ok) {
                        this._editing.focusX = this.current.focusX;
                        this._editing.focusY = this.current.focusY;
                        this._editing.zoom = this.current.zoom;
                        this.open = false;
                    }
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>
@endpush
@endonce
