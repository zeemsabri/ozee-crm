<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PermissionDeniedException extends Exception
{
    protected $permission;
    protected $projectId;

    /**
     * Create a new permission denied exception.
     *
     * @param string $permission
     * @param int|null $projectId
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $permission,
        ?int $projectId = null,
        string $message = 'You do not have the required permission to perform this action.',
        int $code = 403,
        \Throwable $previous = null
    ) {
        $this->permission = $permission;
        $this->projectId = $projectId;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the permission that was denied.
     *
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * Get the project ID if this was a project-specific permission.
     *
     * @return int|null
     */
    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render(): JsonResponse
    {
        $response = [
            'message' => $this->getMessage(),
            'error' => 'permission_denied',
            'permission' => $this->permission
        ];

        if ($this->projectId) {
            $response['project_id'] = $this->projectId;
        }

        return response()->json($response, $this->getCode());
    }
}
