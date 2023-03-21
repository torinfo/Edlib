<?php

namespace App\Models;

enum ContentUserRole: string
{
    case Owner = 'owner';
    case Collaborator = 'collaborator';
}
