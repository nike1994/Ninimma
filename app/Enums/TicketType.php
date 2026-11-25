<?php

namespace App\Enums;

enum TicketType: string
{
    case Bug = 'bug';
    case Task = 'task';
    case Article = 'article';
    case Campaign = 'campaign';
    case SiteReview = 'site_review';
    case SeoContent = 'seo_content';
    case Social = 'social';
    case Other = 'other';

    public function label(): string
    {
        return __('type.'.$this->value);
    }
}
