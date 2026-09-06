<?php

use App\Domain\Enums\ObligationStatus;
use App\Domain\Queries\RecordStatusQuery;
use App\Models\Obligation;
use App\Models\Record;
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
