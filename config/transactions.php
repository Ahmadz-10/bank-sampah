<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Block overselling
    |--------------------------------------------------------------------------
    | Stock of a waste category = weight deposited - weight sold (voided records
    | are ignored). When true, a sale, or voiding a deposit, that would make
    | stock negative is refused. Set to false to allow negative stock.
    */
    'block_oversell' => true,

];
