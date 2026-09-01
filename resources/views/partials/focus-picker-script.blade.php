{{-- The Alpine component behind every focal-point picker (event slideshow
     images, product images). Included by each picker partial; @once means
     including it more than once on a page still emits one copy.

     Callers pass mediaBase (the model's /media URL), csrf, images, and
     optionally ratios for the crop previews. --}}
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
            ratios: config.ratios ?? [
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
