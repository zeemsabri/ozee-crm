<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Classic project page
    |--------------------------------------------------------------------------
    |
    | The pre-redesign Vue project page, kept live alongside the React portal
    | while the new one is being proven. When true, /projects/classic/{code}
    | and its endpoints are registered, and the portal project page shows an
    | "Open the classic version" link.
    |
    | Set PORTAL_CLASSIC=false once the new portal is trusted: the link
    | disappears and the routes go with it, so the old proposal endpoints stop
    | being reachable at the same moment the link does. Deleting the controller
    | and Pages/Public/ProjectView.vue is the follow-up.
    |
    | Routes are registered from config, so run `php artisan route:clear` after
    | changing this — a cached route file has the old choice baked in.
    |
    */

    'classic' => (bool) env('PORTAL_CLASSIC', true),

];
