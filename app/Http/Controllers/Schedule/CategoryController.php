<?php

namespace App\Http\Controllers\Schedule;

class CategoryController extends ScheduleResourceController
{
    protected string $resource = 'categories';

    protected array $columns = ['Bidang'];

    protected array $with = ['subject:id,name,room_id', 'subject.room:id,name'];
}
