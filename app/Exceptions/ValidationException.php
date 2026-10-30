<?php

/**
 * MiniDesk - ValidationException
 *
 * Levée quand les données d'entrée sont invalides. Le routeur la traduit
 * en réponse HTTP 422 avec le détail des erreurs par champ.
 */
class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $errors Messages d'erreur indexés par nom de champ
     */
    public function __construct(private array $errors, string $message = 'Données invalides.')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
