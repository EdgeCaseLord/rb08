/** @type {import('tailwindcss').Config} */
const preset = require('./vendor/filament/filament/tailwind.config.preset.js');

module.exports = {
    presets: [preset],
    content: [
        './app/**/*.{php,js}',
        './resources/**/*.{php,js,blade.php,css}',
        './vendor/filament/**/*.{php,js,blade.php}'
    ],
};
