<?php

namespace App\Http\Controllers\Schedule;

class RoomController extends ScheduleResourceController
{
    protected ?string $usageColumn = 'room_id';

    protected string $resource = 'rooms';
}
