<?php

namespace App\Models;

use Database\Factories\EmailTemplateFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

#[Fillable(['name', 'subject', 'body'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    public function renderBody(string $greeting, string $questionsHtml): string
    {
        return RichContentRenderer::make($this->body)
            ->mergeTags([
                'greeting' => new HtmlString(e($greeting)),
                'questions' => new HtmlString($questionsHtml),
            ])
            ->toHtml();
    }
}
