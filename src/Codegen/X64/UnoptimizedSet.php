<?php

declare( strict_types = 1 );

namespace Codegen\X64;

/**
 * Instruction set.
 */
class UnoptimizedSet {
   public array $instructions;

   public function __construct() {
      $this->instructions = [];
   }
}
