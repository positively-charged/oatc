<?php

declare( strict_types = 1 );

namespace Codegen\Php;

class PhpScript {
   public array $classes = [];
   public array $funcs = [];
   public array $symbols = [];
   /** @var PhpFunc[] */
   public array $funcsToPhpfuncs = [];
}

class PhpFunc {
   public string $name;
   public ?PhpBlockStmt $body;
   public array $bindings;
   public array $params;
   public array $vars;
   public bool $static;
   public int $spec;
   #public ?CStruct $struct;
   public array $pointers;
   public ?PhpType $returnType;

   public function __construct() {
      $this->body = null;
      $this->bindings = [];
      $this->params = [];
      $this->vars = [];
      $this->static = false;
      #$this->spec = SPEC_VOID;
      $this->struct = null;
      $this->pointers = [];
      $this->returnType = null;
   }
}

const PHP_TYPE_INT = 0;
const PHP_TYPE_BOOL = 1;
const PHP_TYPE_STRING = 2;

class PhpNode {}

class PhpBlockStmt extends PhpNode {
   public array $items;
   public array $cleanupItems;
   public ?PhpBlockStmt $cleanup;
   public array $allocs;
   public bool $groupOnly;
   public PhpStmtReturnValue $returnValue;

   public function __construct() {
      $this->items = [];
      $this->cleanupItems = [];
      $this->cleanup = null;
      $this->allocs = [];
      $this->groupOnly = false;
   }
}

class PhpIfStmt extends PhpNode {
   public PhpExpr $cond;
   public PhpNode $body;
   public PhpNode $else;
}

class PhpStmtReturnValue extends PhpNode {
   public PhpNode $value;
   public ?PhpVar $var = null;
}

class PhpExprStmt extends PhpNode {
   public PhpExpr $expr;
}

class PhpStmtWrapper extends PhpNode {
   public PhpNode $stmt;
}

class PhpExpr {
   public array $calls;
   public array $allocs;
   public ?PhpVar $result;
   public PhpNode $root;
   public int $type;
   public int $outputVar;
   public ?PhpStruct $struct = null;
   public ?PhpAlloc $alloc = null;
   public bool $returnsValue = false;

   public function __construct() {
      $this->calls = [];
      $this->allocs = [];
      $this->result = null;
   }
}

const PHP_BINARY_EQ = 0;
const PHP_BINARY_NEQ = 1;
const PHP_BINARY_LT = 2;
const PHP_BINARY_LTE = 3;
const PHP_BINARY_GT = 4;
const PHP_BINARY_GTE = 5;
const PHP_BINARY_ADD = 6;
const PHP_BINARY_SUB = 7;
const PHP_BINARY_MUL = 8;
const PHP_BINARY_DIV = 9;
const PHP_BINARY_MOD = 10;
const PHP_BINARY_LOGAND = 11;
const PHP_BINARY_LOGOR = 12;

class PhpBinary extends PhpNode {
   public PhpNode $lside;
   public PhpNode $rside;
   public PhpVar $result;
   public int $op;
}

class PhpVar extends PhpNode {
   public PhpExpr $value;
   public int $index;
}

class PhpVarUsage extends PhpNode {
   public PhpVar $var;
}

const PHP_CALL_PRINTF = 0;
const PHP_CALL_USER = 1;
const PHP_CALL_OPERAND = 2;

class PhpCall extends PhpNode {
   public int $func;
   public PhpNode $operand;
   /** @var PhpExpr[] */
   public array $args;

   public function __construct() {
      $this->args = [];
   }
}

class PhpIntegerLiteral extends PhpNode {
   public int $value;
}

class PhpBoolLiteral extends PhpNode {
   public bool $value;
}

class PhpStringLiteral extends PhpNode {
   public string $value;
}

class PhpParen extends PhpNode {
   public PhpExpr $expr;
}
