<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\ServerFactory;
use Illuminate\Http\Request;


class ImageController extends Controller
{
    public function show(Request $request, Filesystem $filesystem, $path)
    {
        //dd($request->all(), $path);
        $server = ServerFactory::create([
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => $filesystem->getDriver(),
            'cache' => $filesystem->getDriver(),
            'cache_path_prefix' => '.cache',
            'base_url' => 'glide',
        ]);

        return $server->getImageResponse($path, request()->except(['expires', 'signature']));
    }

    public function showpublic(Request $request, FilesystemManager $fs, $path)
    {
        $filesystem = $fs->disk('webroot');
        //dd($request->all(), $path);
        $server = ServerFactory::create([
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => $filesystem->getDriver(),
            'cache' => $filesystem->getDriver(),
            'cache_path_prefix' => '.cache',
            'base_url' => 'glide',
        ]);

        return $server->getImageResponse($path, request()->except(['expires', 'signature']));
    }
}
