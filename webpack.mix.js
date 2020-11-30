const mix = require('laravel-mix')

require('vuetifyjs-mix-extension')

mix.js('resources/js/app.js', 'public/js').vuetify()
//if you use vuetify-loader
// mix.js('resources/js/app.js', 'public/js').vuetify('vuetify-loader')