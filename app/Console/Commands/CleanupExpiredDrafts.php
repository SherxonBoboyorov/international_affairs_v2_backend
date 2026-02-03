<?php

namespace App\Console\Commands;

use App\Models\ArticleReviewerAssignment;
use Illuminate\Console\Command;

class CleanupExpiredDrafts extends Command
{
    protected $signature = 'drafts:cleanup';
    protected $description = 'Clean up expired drafts';

    public function handle()
    {
        $expiredDrafts = ArticleReviewerAssignment::draftExpired()->get();

        foreach ($expiredDrafts as $assignment) {
            $assignment->clearDraft();
        }

        $this->info("Cleaned up {$expiredDrafts->count()} expired drafts");
    }
}
