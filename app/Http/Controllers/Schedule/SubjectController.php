<?php

namespace App\Http\Controllers\Schedule;

class SubjectController extends ScheduleResourceController
{
    protected string $resource = 'subjects';

    protected array $columns = ['Ruangan'];

    protected array $with = ['room:id,name'];
}
