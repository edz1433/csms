<?php

/*
|--------------------------------------------------------------------------
| RIS document defaults (Requisition and Issue Slip)
|--------------------------------------------------------------------------
| Fixed signatories printed on every RIS. Edit these to match the current
| officials. "Requested By" / "Received By" are left blank on the form for
| the requesting office to sign, with the destination shown as designation.
*/

return [
    'approved_by' => [
        'name' => 'ALADINO C. MORACA, Ph.D.',
        'designation' => 'PRESIDENT',
    ],
    'issued_by' => [
        'name' => 'RAZEL C. MAMAR',
        'designation' => 'SUPPLY OFFICER',
    ],

    // Minimum number of item rows to render (blank rows keep the form shape).
    'min_rows' => 12,

    /*
    | Report of Supplies and Materials Issued (Appendix 64) signatories.
    | "Certified correct by" = Supply Officer; "Posted by" = Accounting Staff.
    */
    'rsmi' => [
        'certified_by' => 'RAZEL C. MAMAR, MPA',
        'posted_by' => 'ERFA B. GETONZO, CPA',
    ],
];
