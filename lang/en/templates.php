<?php

return [
    'labels' => [
        'gentle_reminder' => 'Gentle reminder',
        'firm_reminder' => 'Firm reminder',
        'receipt_thanks' => 'Receipt thanks',
        'hardship_request' => 'Request a pause',
        'forgiveness_notice' => 'Forgiveness notice',
        'share_invite' => 'Share invitation',
    ],
    'gentle_reminder' => 'Hi :name, a gentle reminder about :purpose (:amount), due :due. Payment details: :destination',
    'firm_reminder' => 'Hi :name, :purpose (:amount) was due :due. Please let me know when you can settle it. Payment details: :destination',
    'receipt_thanks' => 'Thank you, :name. I received :amount for :purpose.',
    'hardship_request' => 'Hi :name, I am having difficulty paying :amount for :purpose by :due. Could we discuss a pause or a new date?',
    'forgiveness_notice' => 'Hi :name, I have forgiven the remaining :amount for :purpose. No payment is needed for this balance.',
    'share_invite' => 'Hi :name, here is the promise record for :purpose (:amount): :share_url. Payment details: :destination',
    'no_due_date' => 'no due date set',
];
