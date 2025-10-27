<?php

namespace Kreatif\StatamicForms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Statamic\Forms\Submission;

class FormProcessingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Submission $submission,
        public array $results
    ) {
    }
}
