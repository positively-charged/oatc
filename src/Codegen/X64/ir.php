<?php

declare( strict_types = 1 );

namespace Codegen\X64;

// 64-bit registers.
const REG_RAX = 0;
const REG_RBX = 1;
const REG_RCX = 2;
const REG_RDX = 3;
const REG_RSI = 4;
const REG_RDI = 5;
const REG_RBP = 6;
const REG_RSP = 7;
const REG_R8 = 8;
const REG_R9 = 9;
const REG_R10 = 10;
const REG_R11 = 11;
const REG_R12 = 12;
const REG_R13 = 13;
const REG_R14 = 14;
const REG_R15 = 15;
const REG_TOTAL = 16;

// 32-bit registers.
const REG_EAX = REG_RAX + 1;
const REG_EBX = REG_RBX + 1;
const REG_ECX = REG_RCX + 1;
const REG_EDX = REG_RDX + 1;
const REG_ESI = REG_RSI + 1;
const REG_EDI = REG_RDI + 1;
const REG_EBP = REG_RBP + 1;
const REG_ESP = REG_RSP + 1;
const REG_R8D = REG_R8 + 1;
const REG_R9D = REG_R9 + 1;
const REG_R10D = REG_R10 + 1;
const REG_R12D = REG_R12 + 1;
const REG_R13D = REG_R13 + 1;
const REG_R14D = REG_R14 + 1;
const REG_R15D = REG_R15 + 1;

// 16-bit registers.
const REG_AX = 6;

// 8-bit registers.
const REG_AL = 100;
const REG_BL = 101;
const REG_CL = 102;

const REG_AH = 200;
const REG_BH = 201;
const REG_CH = 202;

// Opcodes.
const OP_NOP = 0;
const OP_CALL = 1;
const OP_RET = 2;
const OP_MOV_R64I64 = 3;
const OP_MOV_R64R64 = 4;
const OP_MOV_R64M64 = 5;
const OP_ADD_R64R64 = 6;
const OP_ADD_R64I64 = 7;
const OP_CMP_R64I64 = 8;
const OP_CMP_R64R64 = 9;
const OP_JZ_I64 = 10;
const OP_SUB_RR = 12;
const OP_SUB_RI = 13;
const OP_LABEL = 14;
const OP_JMP = 15;
const OP_JZ = 16;
const OP_TEST = 17;
const OP_MOVZX_R64R8 = 18;
const OP_MOV_SLOT = 19;
const OP_MOV_RET = 20;
const OP_MOV_M64R64 = 21;

const OP_MOV = 100;
const OP_SETSLOT = 101;
const OP_DESTROY = 102;
const OP_PUSH = 103;
const OP_MOV_RI = 104;
const OP_IDIV = 105;
const OP_CMP = 106;
const OP_SETZ_R8 = 107;
const OP_SETNZ_R8 = 108;
const OP_SETL_R8 = 109;

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

// Instruction argument type.
const ARG_IMM = 0; // Immediate
const ARG_REG = 1; // Register
const ARG_MEM = 2; // Memory

class Register {
   public int $id;
   public ?Slot $slot;
   public ?VirtualRegister $virtualRegister;
   public bool $used;
   public bool $immediate;
   public bool $immovable;
   public ?Value $value;

   public function __construct( int $id ) {
      $this->id = $id;
      $this->slot = null;
      $this->virtualRegister = null;
      $this->used = false;
      $this->immediate = false;
      $this->immovable = false;
      $this->value = null;
   }

   public function getLowerHalf(): int {
      switch ( $this->id ) {
      case REG_RAX: return REG_AL;
      case REG_RBX: return REG_BL;
      case REG_RCX: return REG_CL;
      }
   }

   public function store( Value $value ): ?Value {
      $oldValue = $this->value;
      $this->value = $value;
      return $oldValue;
   }

   public function containsValidValue(): bool {
      return ( $this->value !== null && $this->value->refCount > 0 );
   }
}

class VirtualRegister {
   // Actual x64 register.
   public int $reg;
}

class Assembly {
   /** @var StringEntry[] */
   public array $strings = [];
   /** @var Func[] */
   public array $funcs = [];
   public Func $printf;
}

class StringEntry {
   public int $index;
   public string $value;
}

class IndexedString {
   public int $index;
   public string $value;
}

class DataEntry {
   public string $name;
   public int $type;
}

class Func {
   public string $name;
   public ?Sequence $body = null;
   public bool $global = false;
   public bool $extern = false;
   public bool $inline = false;
   public bool $variadic = false;
   public bool $returnsValue = false;
   /** @var Param[] */
   public array $params = [];
   /** @var Block[] */
   public array $blocks = [];
   public ?\Func $func = null;
   public int $localSize = 0;
}

class Param {
   public string $name;
   public int $type;
   public int $reg;
   public int $offset;
   public Slot $slot;
   public bool $argOnStack = false;
}

class Block {
   public int $id;
   /** @var Instruction[] */
   public array $instructions = [];
   public ?Jump $exitJump = null;
   public bool $enteredViaJump = false;
   /** The block first entered from. */
   public ?Block $entrance = null;
   /** The child block that re-enters the block. */
   public ?Block $entranceCycle = null;
   public ?Block $next = null;
   public string $comment = '';

   public ?Block $parent = null;
   /** @var Block[] */
   public array $parents = [];
   /** @var Block[] */
   public array $children = [];

   public array $values = [];
   /** @var Value[] */
   public array $liveValues = [];
   public array $defs = [];

   public bool $valueUsageDetermined = false;

   public function addValueUsage( Value $value ): void {
      if ( ! in_array( $value, $this->values ) ) {
         array_push( $this->values, $value );
      }
   }

   public function defineValue( Value $value ): void {
      array_push( $this->defs, $value );
   }

   public function propogateValueUsage( Value $value ): void {
      if ( ! in_array( $value, $this->defs ) ) {
         foreach ( $this->parents as $parent ) {
            if ( ! in_array( $value, $parent->liveValues ) ) {
               array_push( $parent->liveValues, $value );
            }
         }
      }
   }

   public function addParent( Block $parent ): void {
      if ( ! in_array( $parent, $this->parents ) ) {
         $this->parents[] = $parent;
      }
   }
   public function addChild( Block $child ): void {
      if ( ! in_array( $child, $this->children ) ) {
         $this->children[] = $child;
      }
   }

   public function descendentsUsageDetermined(): bool {
      foreach ( $this->children as $child ) {
         if ( ! $child->valueUsageDetermined ) {
            return false;
         }
      }
      return true;
   }
}

const JUMP_IF = 0;
const JUMP_IFNOT = 1;

class Jump {
}

class GotoJump extends Jump {
   public ?Block $dst = null; // Destination.
}

const COND_JUMP_ZERO = 0;
const COND_JUMP_NOT_ZERO = 1;

class ConditionalJump extends Jump {
   public function __construct(
      public int $cond,
      public Block $dst, // Destination.
   ) {}
}

class ReturnJump extends Jump {
   public ?Slot $value = null;
}

// Represents a variable in a stack frame.
class Slot {
   public int $id;
   public int $type;
   public ?int $value = null;
   public int $offset;
   public int $firstSeen;
   public int $lastSeen;
   public bool $allocated = false;
   public bool $critical = false;
   public bool $useful = false;
   public bool $folded = false;
   /** @var Slot[] */
   public array $dependencies = [];
   public ?Allocation $allocation = null;
}

class SetImmInstruction extends Instruction {
   public int $value;
   public Slot $destination;

   public function __construct() {
      parent::__construct( OP_SETIMM );
   }
}

class SetSlotInstruction extends Instruction {
   public VirtualRegister $source;
   public Slot $destination;
   public int $sourceReg;

   public function __construct() {
      parent::__construct( OP_SETSLOT );
   }
}

class PushInstruction extends Instruction {
   public VirtualRegister $reg;

   public function __construct() {
      parent::__construct( OP_PUSH );
   }
}

const MOV_MEM_TO_REG = 0;
const MOV_REG_TO_MEM = 1;
const MOV_IMM_TO_REG = 2;
const MOV_REG_TO_REG = 3;
const MOV_REG_TO_REG_ZX= 4;

const OPERAND_IMM = 0;
const OPERAND_MEM = 1;
const OPERAND_REG = 2;

class Operand {
   public int $type;
}

class ImmediateOperand extends Operand {
   public function __construct(
      public int $value,
   ) {}
}

class MemoryOperand extends Operand {
   public function __construct(
      public int $addr,
   ) {}
}

class RegisterOperand extends Operand {
   public function __construct(
      public int $reg,
   ) {}
}

class MachineInstruction extends Instruction {
   //public int $opcode;
   public array $operands = [];

   public function __construct( int $opcode ) {
      parent::__construct( $opcode );
      $this->operands = [];
      $this->comment = '';
   }

   public function appendImm( int $value ): void {
      $operand = new ImmediateOperand( $value );
      $this->operands[] = $operand;
      parent::appendImm( $value );
   }

   public function appendReg( int $reg ): void {
      $operand = new RegisterOperand( $reg );
      $this->operands[] = $operand;
      parent::appendReg( $reg );
   }

   public function appendMemory( int $addr ): void {
      $operand = new MemoryOperand( $addr );
      $this->operands[] = $operand;
      parent::appendMem( $addr );
   }
}

class MovInstruction extends Instruction {
   public int $type;
   public Value $dst;
   public Value $src;

   public function __construct() {
      parent::__construct( OP_MOV );
      $this->type = MOV_IMM_TO_REG;
   }
}

class MovSlotInstruction extends Instruction {
   public Slot $dst;
   public Value $value;

   public function __construct() {
      parent::__construct( OP_MOV_SLOT );
   }
}

class MovRetInstruction extends Instruction {
   public Value $value;

   public function __construct() {
      parent::__construct( OP_MOV_RET );
   }
}

class MovRIInstruction extends Instruction {
   public int $reg;
   public int $immediate;

   public function __construct() {
      parent::__construct( OP_MOV_RI );
   }
}

class SetStrInstruction extends Instruction {
   public IndexedString $string;
   public Slot $destination;

   public function __construct() {
      parent::__construct( OP_SETSTR );
   }
}

class DestroyInstruction extends Instruction {
   public VirtualRegister $reg;

   public function __construct() {
      parent::__construct( OP_DESTROY );
   }
}

class CallInstruction extends Instruction {
   public Func $func;
   /** @var Value[] */
   public array $args;
   public ?Value $returnValue;

   public function __construct() {
      parent::__construct( OP_CALL );
      $this->args = [];
      $this->returnValue = null;
   }
}

class RetInstruction extends Instruction {
   public ?VirtualRegister $value;

   public function __construct() {
      parent::__construct( OP_RET );
      $this->value = null;
   }
}

class BinaryInstruction extends Instruction {
   public VirtualRegister $lside;
   public VirtualRegister $rside;
   public int $lsideReg;
   public int $rsideReg;

   public function __construct( int $opcode ) {
      parent::__construct( $opcode );
   }
}

class AddInstruction extends Instruction {
   public Value $dst;
   public Value $src;
   public Value $result;

   public function __construct() {
      parent::__construct( OP_ADD );
   }
}

class IdivInstruction extends Instruction {
   public int $divisor;

   public function __construct() {
      parent::__construct( OP_IDIV );
   }
}

class CmpInstruction extends Instruction {
   public Value $lside;
   public Value $rside;

   public function __construct() {
      parent::__construct( OP_CMP );
   }
}

class TestInstruction extends Instruction {
   public Value $lside;
   public Value $rside;

   public function __construct() {
      parent::__construct( OP_TEST );
   }
}

class SetInstruction extends Instruction {
   public const SET_Z = 0;
   public const SET_NZ = 1;
   public const SET_L = 2;

   public Value $result;
   public int $type;

   public function __construct( int $type ) {
      parent::__construct( OP_SET );
      $this->type = $type;
   }
}
