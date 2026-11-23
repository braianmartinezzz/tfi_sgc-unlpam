<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CodigoMfaMail extends Mailable
{
    public function __construct(public string $codigo, public int $minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código de verificación - Sistema de Gestión de Convenios');
    }

    public function content(): Content
    {
        $codigo = e($this->codigo);

        return new Content(
            htmlString: "<p>Tu código de verificación es:</p>"
                ."<p style=\"font-size:24px;letter-spacing:4px\"><strong>{$codigo}</strong></p>"
                ."<p>Vence en {$this->minutos} minutos. Si no intentaste iniciar sesión, ignorá este mensaje.</p>"
        );
    }
}
