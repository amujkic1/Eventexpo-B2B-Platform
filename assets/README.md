# UI Assets

The interface follows the supplied EventExpo B2B redesign. Styles and JavaScript
are scoped to the plugin's `ee-app` container; the WordPress theme remains in
control of the surrounding page.

Poppins fonts are bundled from the supplied reference and use the license in
`fonts/OFL.txt`. The SVG sprite contains the selected icons from
`lucide-static` 1.61.0; its license is in `icons/LICENSE`.

There is no asset build step or external runtime dependency. WordPress enqueues
`ui.css` and `ui.js`, using file modification times for cache invalidation.
