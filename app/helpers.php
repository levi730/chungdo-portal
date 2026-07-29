<?php
use Illuminate\Support\Str;
function arr_find_key($arr, $callable)
{
    foreach ($arr as $k => $v) {
        if ($callable($v)) {
            return $k;
        }
    }

    return false;
}


function glideUrlFromMedia(\Spatie\MediaLibrary\MediaCollections\Models\Media $m, $glideArgs=null) {
    $clsname = config("media-library.path_generator");
    $g = new $clsname();

    // The 'public' media disk (storage/app/public) is served under /storage via
    // the storage:link symlink, so Glide (rooted at public/) must read it there.
    $url = url("glide/public/storage/" . $g->getPath($m) . $m->file_name);
    if($glideArgs) {
        if(!Str::startsWith($glideArgs,'?')) {
            $glideArgs = '?' . $glideArgs;
        }
        $url .= $glideArgs;
    }
    return $url;
}

function glideCropUrlFromMedia(\Spatie\MediaLibrary\MediaCollections\Models\Media $m, $width, $height, $extraGlideParams=null) {

    $glideArgs = '?w=' . $width . '&h=' . $height . '&fit=crop';

    $focusX = $m->getCustomProperty('focusX');
    $focusY = $m->getCustomProperty('focusY');

    // Glide focal crop: fit=crop-{x}-{y}[-{zoom}], x/y as 0-100 percentages.
    // Check !== null so an edge focus (0) still counts as set.
    if ($focusX !== null && $focusY !== null) {
        $glideArgs .= '-' . round($focusX) . '-' . round($focusY);
        $zoom = $m->getCustomProperty('focusZoom');
        if ($zoom && $zoom > 1) {
            $glideArgs .= '-' . $zoom;
        }
    }
    if ($extraGlideParams) {
        $glideArgs .= '&' . $extraGlideParams;
    }
    return glideUrlFromMedia($m, $glideArgs);
}
