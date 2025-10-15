<?php

declare(strict_types=1);

namespace YcPca\Security\Detectors;

use YcPca\Security\SecurityContext;

/**
 * Interface for all security vulnerability detectors
 */
interface DetectorInterface
{
    /**
     * Detect vulnerabilities in the AST
     *
     * @param array $ast The parsed AST
     * @param SecurityContext $context Security analysis context
     * @param array &$vulnerabilities Array to append found vulnerabilities
     */
    public function detect(array $ast, SecurityContext $context, array &$vulnerabilities): void;

    /**
     * Check if the detector has executed
     *
     * @return bool
     */
    public function hasExecuted(): bool;
}