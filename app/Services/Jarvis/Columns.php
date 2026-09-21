<?php

namespace App\Services\Jarvis;

/**
 * The allow-lists. Every JARVIS query names its columns from here — nothing ever
 * runs `select *` on end_users, because that table holds the crown jewels and a
 * column added next year would silently start leaving the building.
 *
 * What must never reach JARVIS, and why it is worth naming out loud:
 *   ssn, ssn_picture_path            — identity theft in one field
 *   date_of_birth, ghl_dob_raw       — same
 *   current_address, address_line2, zipcode — a person's front door
 *   phone, email                     — contact details the assistant has no use for
 *   credit_monitoring_username/password/pin/security_question/security_answer,
 *   cfpb_email, cfpb_password, cfpb_round_credentials — live logins to other systems
 *   photo_id_path, proof_of_address_path, collage_path — document scans
 *   intake_submitted_ip              — where they were sitting
 *
 * City and state ARE allowed (useful, not identifying on their own). Names are
 * allowed — JARVIS has to be able to say "Clinecea's client Dominique is overdue".
 */
final class Columns
{
    /**
     * end_users columns any JARVIS endpoint may read.
     *
     * Note what is absent as much as what is present. `last4_ssn` is NOT here:
     * the spec allowed it if genuinely needed, and nothing here needs it, so it
     * is omitted entirely rather than shipped "just in case".
     */
    public const END_USER = [
        'id',
        'client_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'city',
        'state',
        'status',
        'intake_status',
        'error_type',
        'held_at',
        'rounds',
        'round_dates',
        'next_round_override',
        'round_approval_status',
        'round_approval_round',
        'current_score',
        'goal_score',
        'start_date',
        'created_at',
        'updated_at',
        'ghl_contact_id',
        'disputefox_pushed_at',
    ];

    /** clients (the credit-repair companies, NOT consumers). No password, no intake_api_key. */
    public const BUSINESS_OWNER = [
        'id',
        'business_name',
        'status',
        'compensation_model',
        'round_cycle_days',
        'results_tracking',
        'created_at',
        'updated_at',
    ];

    /**
     * Tables JARVIS may never touch, in any query, join or subquery.
     * `business_owner_credentials` holds the owners' live logins to other systems —
     * it is the single most dangerous table in this database.
     */
    public const FORBIDDEN_TABLES = [
        'business_owner_credentials',
    ];

    /**
     * Keys that must never appear in a JARVIS response body, whatever the source.
     * The PII test asserts against this list, so adding a field here tightens
     * every endpoint at once.
     */
    public const FORBIDDEN_RESPONSE_KEYS = [
        'ssn', 'last4_ssn', 'ssn_picture_path',
        'dob', 'date_of_birth', 'ghl_dob_raw',
        'address', 'current_address', 'address_line2', 'zipcode', 'zip',
        'phone', 'email',
        'password', 'credit_monitoring_username', 'credit_monitoring_password',
        'credit_monitoring_pin', 'credit_monitoring_security_question',
        'credit_monitoring_security_answer',
        'cfpb_email', 'cfpb_password', 'cfpb_round_credentials',
        'photo_id_path', 'proof_of_address_path', 'collage_path',
        'intake_submitted_ip',
    ];
}
