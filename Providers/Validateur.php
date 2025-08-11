<?php

namespace App\Providers;

use App\Models;

class Validator
{
    private array $errors = [];
    private string $key;
    private mixed $value;
    private string $name;

    /**
     * Définir un champ à valider
     */
    public function field( $key, $value, $name = null): static
    {
        $this->key = $key;
        $this->value = $value;
        $this->name = $name ? ucfirst($name) : ucfirst($key);
        return $this;
    }

    /**
     * Vérifie que le champ est requis
     */
    public function required(): static
    {
        if (empty($this->value)) {
            $this->errors[$this->key] = "$this->name is required!";
        }
        return $this;
    }

    /**
     * Vérifie la longueur maximale
     */
    public function max(int $length): static
    {
        if (strlen($this->value) > $length) {
            $this->errors[$this->key] = "$this->name must be less than $length characters!";
        }
        return $this;
    }

    /**
     * Vérifie la longueur minimale
     */
    public function min(int $length): static
    {
        if (strlen($this->value) < $length) {
            $this->errors[$this->key] = "$this->name must be more than $length characters!";
        }
        return $this;
    }

    /**
     * Vérifie que la valeur est un nombre
     */
    public function number(): static
    {
        if (!empty($this->value) && !is_numeric($this->value)) {
            $this->errors[$this->key] = "$this->name must be a number!";
        }
        return $this;
    }

    /**
     * Vérifie que l'email est valide
     */
    public function email(): static
    {
        if (!empty($this->value) && !filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$this->key] = "$this->name invalid!";
        }
        return $this;
    }

    /**
     * Vérifie l’unicité avec un modèle donné (ex: 'User')
     */
    public function unique(string $model): static
    {
        $modelClass = 'App\\Models\\' . $model;
        $instance = new $modelClass;

        if (method_exists($instance, 'unique')) {
            if ($instance->unique($this->key, $this->value)) {
                $this->errors[$this->key] = "$this->name must be unique!";
            }
        }
        return $this;
    }

    /**
     * Retourne true si aucune erreur
     */
    public function isSuccess(): bool
    {
        return empty($this->errors);
    }

    /**
     * Retourne les erreurs si échec
     */
    public function getErrors(): array|null
    {
        return $this->isSuccess() ? null : $this->errors;
    }
}
