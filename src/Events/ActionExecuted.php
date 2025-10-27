<?php

namespace Kreatif\StatamicForms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Statamic\Forms\Submission;

class ActionExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $actionClass,
        public Submission $submission,
        public ActionResult $result
    ) {
    }
}
