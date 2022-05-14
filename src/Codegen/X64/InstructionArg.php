<?php

declare( strict_types = 1 );

namespace Codegen\X64;

const ARG_IMM = 0;  // Immediate
const ARG_SLOT = 1; // Slot
const ARG_FUNC = 2; // Slot

class InstructionArg {
   public int $type;
   public mixed $value;
   public ?VirtualRegister $reg;

   public function __construct( int $type, mixed $value ) {
      $this->type = $type;
      $this->value = $value;
      $this->reg = null;
   }
}
