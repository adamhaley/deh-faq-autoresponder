<?php

namespace App\Models;

use Database\Factories\EmailTemplateFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

#[Fillable(['user_id', 'name', 'subject', 'body'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The template to use for a given user: their own personal template if
     * they have one, else the shared fallback (the row with no owner).
     */
    public static function forUser(?int $userId): ?self
    {
        $personal = $userId !== null
            ? static::query()->where('user_id', $userId)->first()
            : null;

        return $personal ?? static::query()->whereNull('user_id')->first();
    }

    public function renderBody(string $questionsHtml): string
    {
        return RichContentRenderer::make($this->body)
            ->mergeTags([
                'questions' => new HtmlString($questionsHtml),
            ])
            ->toHtml();
    }
}
