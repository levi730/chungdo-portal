const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.webpackConfig({
    output: {
        chunkFilename: 'js/[name].js?id=[chunkhash]',
    }
})
//mix.browserSync('localhost:8000');
let proxy_url = process.env.MIX_APP_URL;
mix.browserSync(process.env.MIX_APP_URL);

mix.js('resources/js/app.js', 'public/js').vue()
    .js('resources/js/barcode_scanner.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        //
    ])
    .postCss('resources/css/email.css', 'public/css', [
        //
    ]);

if (mix.inProduction()) {
    mix.version();
}
