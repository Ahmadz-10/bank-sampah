<?php

/*
|--------------------------------------------------------------------------
| How the monthly waste-reduction figures are calculated
|--------------------------------------------------------------------------
| Source: the RW weighing sheet (kg per day by group, times the days counted).
|
|   total_waste_generated = every group
|   waste_diverted        = the groups listed in `diverted_groups`
|   waste_to_tps          = the groups listed in `to_tps_groups`
|   reduction_percentage  = waste_diverted / total_waste_generated x 100
|
| These are DEFAULTS pending confirmation from the kelurahan. Group names:
| organik, anorganik, b3, residu. B3 (hazardous) is counted in the total only.
*/

return [
    'diverted_groups' => ['organik', 'anorganik'],
    'to_tps_groups' => ['residu'],

    // Target % given to a new month when no earlier month has one.
    'default_target' => 0,
];
