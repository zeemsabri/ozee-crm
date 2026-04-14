<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize the private project chat channel.
 * Users can subscribe if they belong to the project
 * or have the global 'view_all_projects' permission.
 */
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    if ($user->hasPermission('view_all_projects')) {
        return true;
    }
    return $user->projects()->where('projects.id', $projectId)->exists();
});

/**
 * Authorize the private topic chat channel.
 * Users can subscribe if they belong to the topic's project
 * or have the global 'view_all_projects' permission.
 */
Broadcast::channel('topic.{topicId}', function ($user, $topicId) {
    if ($user->hasPermission('view_all_projects')) {
        return true;
    }
    $topic = \App\Models\TelegramTopic::find($topicId);
    if (!$topic) {
        return false;
    }
    return $user->projects()->where('projects.id', $topic->project_id)->exists();
});
