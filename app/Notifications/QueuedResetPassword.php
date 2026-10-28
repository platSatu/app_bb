<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Email reset password lewat antrean (lupa password & akun login customer baru). Isi email: AppServiceProvider. */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
