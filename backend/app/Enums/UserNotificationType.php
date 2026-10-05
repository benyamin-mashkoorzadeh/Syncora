<?php

namespace App\Enums;

enum UserNotificationType: string
{
    case TaskAssigned = 'task.assigned';
    case TaskCommentAdded = 'task.comment_added';
}
