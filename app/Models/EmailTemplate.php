<?php

namespace App\Models;

use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'subject', 'body'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    public function renderBody(string $greeting, string $questionsHtml): string
    {
        return strtr($this->body, [
            '{{greeting}}' => $greeting,
            '{{questions}}' => $questionsHtml,
        ]);
    }
}
