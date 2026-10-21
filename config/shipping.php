<?php
declare(strict_types=1);
// Area-based prices from Pasig, not measured road distance or courier quotations.
return [
    'origin'=>'Pasig City, Metro Manila',
    'priority_surcharge_cents'=>6000,
    'tiers'=>['pasig'=>8000,'adjacent'=>9000,'metro_manila'=>10000,'nearby_luzon'=>12000,'other_luzon'=>15000,'visayas'=>18000,'mindanao'=>20000],
    'adjacent_cities'=>['mandaluyong','marikina','quezon','taguig','pateros','makati'],
    'metro_cities'=>['pasig','mandaluyong','marikina','quezon','taguig','pateros','makati','manila','pasay','paranaque','las pinas','muntinlupa','caloocan','malabon','navotas','valenzuela','san juan'],
    'nearby_regions'=>['R3','R4A'],
    'luzon_regions'=>['CAR','R1','R2','MIMAROPA','R5'],
    'visayas_regions'=>['R6','NIR','R7','R8'],
    'mindanao_regions'=>['R9','R10','R11','R12','R13','BARMM'],
];
