<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use Fp\FullRoute\Contracts\OrchestratorInterface;

class OrchestratorContext
{
    /** @var array<string, OrchestratorInterface> */
    protected array $orchestrators = [];

    public function register(string $key, OrchestratorInterface $orchestrator): void
    {
        $this->orchestrators[$key] = $orchestrator;
    }

    public function get(string $key): OrchestratorInterface
    {
        if (!isset($this->orchestrators[$key])) {
            throw new \RuntimeException("No se encontró el orquestador con clave: $key");
        }

        return $this->orchestrators[$key];
    }

    public function all(): array
    {
        return $this->orchestrators;
    }
}
