<?php

use App\Features\PromiseTracker\Domain\Enums\ObligationStatus;
use App\Features\PromiseTracker\Domain\Queries\RecordStatusQuery;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\Record;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('record status query accepts enum-casted obligation statuses', function () {
    $obligation = new Obligation(['status' => ObligationStatus::Settled]);
    $obligations = Mockery::mock(HasMany::class);
    $obligations->shouldReceive('pluck')
        ->once()
        ->with('status')
        ->andReturn(collect([$obligation->status]));

    $record = Mockery::mock(Record::class)->makePartial();
    $record->shouldReceive('obligations')
        ->once()
        ->andReturn($obligations);

    expect((new RecordStatusQuery)->forRecord($record))
        ->toBe(ObligationStatus::Settled);
});
