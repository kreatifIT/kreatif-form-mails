<?php

namespace Kreatif\StatamicForms\Contracts;

use Kreatif\StatamicForms\Mail\BaseMailable;
use Statamic\Forms\Submission;

interface FormActionInterface
{
    /**
     * Execute the action with the given submission and configuration.
     *
     * @param Submission $submission
     * @param array $config
     * @return ActionResult
     */
    public function execute(Submission $submission, array $config): ActionResult;

    /**
     * Validate the configuration for this action.
     *
     * @param array $config
     * @return bool
     */
    public function validate(array $config): bool;

    /**
     * Get the default priority for this action (lower runs first).
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Check if this action should be executed based on conditions.
     *
     * @param Submission $submission
     * @param array $config
     * @return bool
     */
    public function shouldExecute(Submission $submission, array $config): bool;

    /**
     * @return class-string<BaseMailable>|null
     */
    public function getMailableClass(): ?string;

    public static function isPreviewable(): bool;

    public static function name(): string;
}
