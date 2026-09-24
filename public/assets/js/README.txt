qrcode.min.js
-------------
Self-contained browser build of the "qrcode" npm package (MIT licence),
bundled with esbuild so the site loads nothing from a CDN. Vendored on
purpose: a CDN link is one more thing that can go down, get blocked, or
change under you — and this page has to work in a temple hall with poor
wifi.

To rebuild after updating the package:

    npm install qrcode
    npm install --save-dev esbuild
    # entry.js:
    #   import QRCode from 'qrcode';
    #   window.TYTQRCode = {
    #     toCanvas:  (el, text, opts) => QRCode.toCanvas(el, text, opts),
    #     toDataURL: (text, opts)     => QRCode.toDataURL(text, opts),
    #   };
    npx esbuild entry.js --bundle --minify --format=iife --outfile=qrcode.min.js
