<?php

namespace App\Http\Controllers\Schedule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RoomController extends ScheduleResourceController
{
    protected ?string $usageColumn = 'room_id';

    protected string $resource = 'rooms';

    /** Sakelar tidak terkirim saat mati, jadi diisi false lebih dulu. */
    protected function prepare(Request $request): void
    {
        $request->merge(['is_online' => $request->boolean('is_online')]);
    }

    protected function extraRules(Request $request, string $branchId, Model $item): array
    {
        return ['is_online' => ['boolean']];
    }
}
