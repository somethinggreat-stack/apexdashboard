<?php

/*
 * DisputeFox web form push — Benny only.
 *
 * DisputeFox has no customer API. The only route in is the web form endpoint,
 * with the fields defined on a form built in their Settings → Web Forms. These
 * ids come from the generated code of the form named DONOTTOUCH, which exists
 * solely for this push. Renaming, editing or deleting that form changes the
 * field names and breaks this silently — hence the name.
 */

return [
    'enabled' => env('DISPUTEFOX_ENABLED', true),

    'url'    => env('DISPUTEFOX_URL', 'https://pulse.disputeprocess.com/CustumFieldController'),
    'method' => env('DISPUTEFOX_METHOD', 'addWebFormData'),

    /*
     * DisputeFox answers a successful submission by echoing back whatever
     * redirect_url was posted, and a failure with a JSON ErrorMessage. The form
     * has no redirect configured, so success would come back as an empty body —
     * indistinguishable from a silent failure.
     *
     * The push therefore always posts this marker and checks it comes back.
     * Nobody ever visits the address; it only has to be recognisable.
     */
    'ack_url' => env('DISPUTEFOX_ACK_URL', 'https://apexgrowthsolution.com/disputefox-accepted'),

    /*
     * Hidden fields carried by the form. Not secrets — they sit in the HTML of
     * any page the form is embedded on — but they identify the account and the
     * form, so they belong in config rather than scattered through code.
     */
    'hidden' => [
        'tab_info_id'             => env('DISPUTEFOX_TAB_INFO_ID', 'NFZaVm5Xb1IvOVgzOHRwSDZvMzk3dz09'),
        'company_id'              => env('DISPUTEFOX_COMPANY_ID', 'aGw3WkNRRVpHdmV5NWdjZUFhWnpQQT09'),
        'cust_type'               => env('DISPUTEFOX_CUST_TYPE', 2),
        'add_affiliate_flag'      => env('DISPUTEFOX_ADD_AFFILIATE_FLAG', 0),
        'assignedto_id'           => env('DISPUTEFOX_ASSIGNEDTO_ID', 32063),
        'sales_representative_id' => env('DISPUTEFOX_SALES_REPRESENTATIVE_ID', -1),
        'workflow_statusid'       => env('DISPUTEFOX_WORKFLOW_STATUSID', 30),
        'folder_statusid'         => env('DISPUTEFOX_FOLDER_STATUSID', 1),
        'customer_statusid'       => env('DISPUTEFOX_CUSTOMER_STATUSID', -1),
        'portalAccess'            => env('DISPUTEFOX_PORTAL_ACCESS', 1),
        'customerAgreementIDs'    => env('DISPUTEFOX_CUSTOMER_AGREEMENT_IDS', 38032),
    ],

    /*
     * The Monitoring Agency field is a <select> posting a number, not the name.
     * Sending "MyScoreIQ" as text lands blank with no error, so the label the
     * client picked in GoHighLevel is translated here. Keys are lowercased and
     * stripped of spaces before lookup.
     */
    'monitoring_agencies' => [
        'identityiq'     => 1,
        'smartcredit'    => 6,
        'privacyguard'   => 7,
        'myscoreiq'      => 8,
        'myfreescorenow' => 9,
    ],

    /*
     * Documents travel as "<browser path>~~~~<data URL>" in an ordinary text
     * field, base64 inside a form-encoded body. That inflates a file by about a
     * third, so a large set can outgrow the receiving server's post limit. Files
     * above this raw size are skipped and reported rather than silently failing
     * the whole push.
     */
    'max_document_bytes' => env('DISPUTEFOX_MAX_DOCUMENT_BYTES', 7 * 1024 * 1024),

    /* end_users column => the form field it is sent in. */
    'documents' => [
        'photo_id_path'         => 'uploadDocument1',   // Driver's License
        'proof_of_address_path' => 'uploadDocument2',   // Proof of Address
        'ssn_picture_path'      => 'uploadDocument3',   // SSN Card
    ],
];
