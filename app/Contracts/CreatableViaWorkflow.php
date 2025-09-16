<?php

namespace App\Contracts;

interface CreatableViaWorkflow
{
    /**
     * Return the required fields for create for this model (DB-aligned minimal set).
     *
     * @return array<string>
     */
    public static function requiredOnCreate(): array;

    /**
     * Compute default values for create, given the workflow context.
     * Only return keys that you wish to provide defaults for.
     *
     * @param array $context
     * @return array<string, mixed>
     */
    public static function defaultsOnCreate(array $context): array;
}
