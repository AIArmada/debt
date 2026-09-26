<?php

use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;

test('direction descriptions match the promise subject type', function (SubjectType $subjectType, Direction $direction, string $description) {
    expect($direction->descriptionFor($subjectType))->toBe($description);
})->with([
    'money payable' => [SubjectType::Money, Direction::Payable, 'I need to pay them.'],
    'money receivable' => [SubjectType::Money, Direction::Receivable, 'I expect to receive payment.'],
    'quantity payable' => [SubjectType::Quantity, Direction::Payable, 'I need to give them an item or time.'],
    'quantity receivable' => [SubjectType::Quantity, Direction::Receivable, 'I expect to receive an item or time.'],
    'commitment payable' => [SubjectType::Commitment, Direction::Payable, 'I need to do something for them.'],
    'commitment receivable' => [SubjectType::Commitment, Direction::Receivable, 'I expect them to do something for me.'],
]);
