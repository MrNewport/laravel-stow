<?php

return [
    // Set before the first migration. Existing tables are never converted.
    'morph_key_type' => 'int', // int, uuid, or ulid

    /*
        * This is where you restrict certain instances to holding only specified Models.
        *
        * If your instance name is not added, it remains unrestricted and will hold any
        * model that implements Stowable
        */

    'instances' => [
        // 'cart' => [ Product::class ]
    ],

];
