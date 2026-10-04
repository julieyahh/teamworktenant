<?php

/*
|--------------------------------------------------------------------------
| Collections & escalation
|--------------------------------------------------------------------------
| READ THIS BEFORE CHANGING ANY NUMBER IN THIS FILE.
|
| This system does NOT enforce a legal rule. It records what the landlord did
| and when, and it flags when a tenant has crossed a threshold the landlord
| configured. A human always makes the escalation decision.
|
| The defaults below are PLACEHOLDERS chosen to be conservative. They are not
| legal advice and were not drafted by a lawyer. Before this goes live, have
| Philippine counsel (or your local equivalent) confirm:
|
|   - the demand/notice period you must give before filing
|   - whether unpaid utilities are a ground for action under your lease, or
|     only unpaid rent
|   - whether the unit falls under the Rent Control Act's coverage (the rent
|     threshold has been revised more than once — do not assume a number)
|   - the correct venue and form for an unlawful detainer filing
|
| Civil Code lease provisions (Arts. 1642-1688) and RA 9653 as extended are
| the usual starting points, but coverage and periods change. Confirm, then
| set the numbers here and record who confirmed them and when.
|
| Confirmed by: ______________________  Date: ____________
*/

return [

    /*
    |----------------------------------------------------------------------
    | Self-help remedies — HARD OFF
    |----------------------------------------------------------------------
    | Cutting utilities, changing locks, or removing a tenant's property to
    | force payment is generally unlawful regardless of how far behind the
    | tenant is. The system has no feature that does any of these and this
    | flag exists so that nobody adds one by accident: EscalationService
    | throws if it is ever turned on.
    |
    | Do not set this to true. If someone asks you to, that is the moment to
    | call a lawyer, not to change a config value.
    */
    'allow_self_help_remedies' => false,

    /*
    |----------------------------------------------------------------------
    | Stages
    |----------------------------------------------------------------------
    | A tenant moves through these as arrears age. Each threshold is "days
    | past the oldest unpaid due date".
    |
    | The system computes the stage and shows it. It never acts on it.
    */
    'stages' => [

        // Nothing owed, or owed but not yet due.
        'current' => [
            'label' => 'Current',
            'after_days' => null,
        ],

        // Past due. Send a friendly reminder — no legal weight.
        'overdue' => [
            'label' => 'Overdue',
            'after_days' => 1,
        ],

        // Old enough that a formal written demand is appropriate. The system
        // prompts; the manager decides and issues it.
        'demand_due' => [
            'label' => 'Formal demand appropriate',
            'after_days' => 15,
        ],

        // A formal demand was issued and its grace period has run out without
        // payment or an agreed arrangement.
        'grace_expired' => [
            'label' => 'Demand grace period expired',
            'after_days' => null, // computed from the demand, not the due date
        ],

        // Everything the landlord can do administratively has been done and
        // documented. The next step is a lawyer, not a button in this app.
        'eligible_for_formal_action' => [
            'label' => 'Eligible for formal action — refer to counsel',
            'after_days' => null, // computed from the demand
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Grace periods, in days, granted by each notice type
    |----------------------------------------------------------------------
    | CONFIRM THESE WITH COUNSEL. The demand period in particular is the one
    | that gets a case thrown out when it is too short.
    */
    'grace_periods' => [
        'reminder'         => 7,
        'formal_demand'    => 15,
        'notice_to_vacate' => 15,
    ],

    /*
    | The order a landlord is expected to follow. The UI will warn — not
    | block — if a step is skipped, because there are legitimate reasons to
    | (a tenant who has already vacated, a negotiated settlement).
    */
    'demand_sequence' => ['reminder', 'formal_demand', 'notice_to_vacate'],

    /*
    | Delivery channels we can record. Anything served in person or by
    | registered mail should be logged here too, with the receipt reference
    | in `delivery_reference` — that is the field that proves service.
    */
    'delivery_channels' => ['email', 'sms', 'hand_delivered', 'registered_mail', 'courier'],

    /*
    | Ignore arrears below this amount when computing stage, so a stray
    | centavo from a rounding remainder never puts a tenant into collections.
    */
    'minimum_actionable_arrears' => 100.00,
];
