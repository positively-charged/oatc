<?php

declare( strict_types = 1 );

namespace Codegen\Oatir;

class Archive {
   /** @var IndexedString[] */
   public array $strings = [];
   /** @var Func[] */
   public array $funcs = [];
   public Func $printf;
}

class IndexedString {
   public int $index;
   public string $value;
}

class Func {
   public string $name;
   public bool $global = false;
   public bool $extern = false;
   /** @var Param[] */
   public array $params = [];
   /** @var Block[] */
   public array $blocks;
   public ?\Func $func = null;
   public bool $variadic = false;
}

class Param {
   public int $type;
   public Slot $slot;
}

// Opcodes of the instructions of the OatIR.
const OP_NOP = 0;
const OP_SET = 1;
const OP_SETVAR = 2;
const OP_SETIMM = 3;
const OP_SETRETVAL = 4;
const OP_RET = 5;
const OP_ADD = 7;
const OP_SUB = 8;
const OP_MUL = 9;
const OP_DIV = 10;
const OP_MOD = 11;
const OP_EQ = 12;
const OP_NEQ = 13;
const OP_LT = 14;
const OP_GT = 15;
const OP_LTE = 16;
const OP_GTE = 17;
const OP_LOGAND = 18;
const OP_LOGOR = 19;
const OP_LOGNOT = 20;
const OP_BITAND = 21;
const OP_BITOR = 22;
const OP_BITXOR = 23;
const OP_LSHIFT = 24;
const OP_RSHIFT = 25;
const OP_CALL = 26;
const OP_GOTO = 28;
const OP_IFGOTO = 29;
const OP_IFNOTGOTO = 30;
const OP_CASEGOTO = 31;
const OP_SETSTR = 32;

const TYPE_I8 = 0;
const TYPE_I16 = 1;
const TYPE_I32 = 2;
const TYPE_I64 = 3;
const TYPE_ISIZE = 4;
const TYPE_U8 = 5;
const TYPE_U16 = 6;
const TYPE_U32 = 7;
const TYPE_U64 = 8;
const TYPE_USIZE = 9;
const TYPE_STR = 10;

class Slot {
   public int $id;
   public int $type;
}

class Instruction {
   public int $opcode;

   public function __construct( int $opcode ) {
      $this->opcode = $opcode;
   }
}

class SetImmInstruction extends Instruction {
   public int $value;
   public Slot $destination;

   public function __construct() {
      parent::__construct( OP_SETIMM );
   }
}

class SetStrInstruction extends Instruction {
   public IndexedString $string;
   public Slot $destination;

   public function __construct() {
      parent::__construct( OP_SETSTR );
   }
}

class CallInstruction extends Instruction {
   public Func $func;
   /** @var Slot[] */
   public array $args;
   public ?Slot $returnValue;

   public function __construct() {
      parent::__construct( OP_CALL );
      $this->args = [];
      $this->returnValue = null;
   }
}

class RetInstruction extends Instruction {
   public ?Slot $value;

   public function __construct() {
      parent::__construct( OP_RET );
      $this->value = null;
   }
}

class BinaryInstruction extends Instruction {
   public Slot $lside;
   public Slot $rside;
   public Slot $result;

   public function __construct( int $opcode ) {
      parent::__construct( $opcode );
   }
}

const ARG_IMM = 0;  // Immediate
const ARG_SLOT = 1; // Slot
const ARG_FUNC = 2; // Slot

class InstructionArg {
   public int $type;
   public mixed $value;

   public function __construct( int $type, mixed $value ) {
      $this->type = $type;
      $this->value = $value;
   }
}

class Block {
   public int $id;
   /** @var Instruction[] */
   public array $instructions = [];
   public ?Jump $exitJump = null;
}

const JUMP_IF = 0;
const JUMP_IFNOT = 1;

class Jump {
   public ?Block $dst = null; // Destination.
   public ?Block $onTrue = null; // Destination.
   public ?Block $onFalse = null; // Destination.
   public ?Slot $cond = null;
}
