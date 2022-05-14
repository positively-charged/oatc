<?php

declare( strict_types = 1 );

use Checking\Value;
use Lexing\Position;
use Typing\Type;

$value = 0;
define( 'NODE_BLOCKSTMT', $value++ );
define( 'NODE_PARAM', $value++ );
define( 'NODE_FUNC', $value++ );
define( 'NODE_INTEGER_LITERAL', $value++ );
define( 'NODE_STRING_LITERAL', $value++ );
define( 'NODE_BOOL_LITERAL', $value++ );
define( 'NODE_TYPE_LITERAL', $value++ );
define( 'NODE_VAR', $value++ );
define( 'NODE_EXPR_STMT', $value++ );
define( 'NODE_BINARY', $value++ );
// 10
define( 'NODE_EXPR', $value++ );
define( 'NODE_NAME_USAGE', $value++ );
define( 'NODE_REFINE', $value++ );
define( 'NODE_ASSIGNMENT', $value++ );
define( 'NODE_CALL', $value++ );
define( 'NODE_ACCESS', $value++ );
define( 'NODE_SUBSCRIPT', $value++ );
define( 'NODE_ENUM', $value++ );
define( 'NODE_ENUMERATOR', $value++ );
define( 'NODE_IF', $value++ );
// 20
define( 'NODE_SWITCH', $value++ );
define( 'NODE_MATCH', $value++ );
define( 'NODE_WHILE', $value++ );
define( 'NODE_FOR', $value++ );
define( 'NODE_FOR_ITEM', $value++ );
define( 'NODE_JUMP', $value++ );
define( 'NODE_RETURN_STMT', $value++ );
define( 'NODE_LOGICAL', $value++ );
define( 'NODE_REFINEMENT', $value++ );
define( 'NODE_STRUCTURE', $value++ );
// 30
define( 'NODE_STRUCTURE_MEMBER', $value++ );
define( 'NODE_TYPE_PARAM', $value++ );
define( 'NODE_CONSTANT', $value++ );
define( 'NODE_TRAIT', $value++ );
define( 'NODE_IMPORT', $value++ );
define( 'NODE_IMPORT_ITEM', $value++ );
define( 'NODE_TYPE_ALIAS', $value++ );
define( 'NODE_LET', $value++ );
define( 'NODE_MOVED_VALUE', $value++ );
define( 'NODE_STRUCTURE_LITERAL', $value++ );
// 40
define( 'NODE_UNARY', $value++ );
define( 'NODE_TUPLE', $value++ );
define( 'NODE_LOGICAL_NOT', $value++ );
define( 'NODE_NULL_POINTER', $value++ );
define( 'NODE_POINTER_CONSTRUCTOR', $value++ );
define( 'NODE_SIZEOF', $value++ );
define( 'NODE_ERR_STMT', $value++ );
define( 'NODE_LIKE', $value++ );
define( 'NODE_SEEN', $value++ );
define( 'NODE_PROPAGATION', $value++ );
define( 'NODE_DROP', $value++ );
define( 'NODE_GENERIC', $value++ );
define( 'NODE_TYPE_EXPR', $value++ );
define( 'NODE_TYPE_VARIANT', $value++ );
define( 'NODE_TYPE_TUPLE', $value++ );
define( 'NODE_BORROW', $value++ );
define( 'NODE_TYPE_CALL', $value++ );

unset( $value );

class Node {
   public int $nodeType;

   public function __construct( int $nodeType ) {
      $this->nodeType = $nodeType;
   }
}

class Import extends Node {
   public bool $visible;
   public Position $pos;
   /** @var ImportSelection[]  */
   public array $selections;

   public function __construct() {
      parent::__construct( NODE_IMPORT );
      $this->selections = [];
      $this->visible = false;
   }
}

class Name {
   public Position $pos;
   public string $value;
}

class ImportSelection {
   public ?Name $alias = null;
   public Path $path;
   public bool $glob = false;
   /** @var ImportSelection[]  */
   public array $selections = [];
   public ?Module $module = null;
   public ?ImportItem $item = null;
}

class ImportItem extends Node {
   public Position $pos;
   public string $name;
   public bool $visible;
   public ?Node $object;

   public function __construct() {
      parent::__construct( NODE_IMPORT_ITEM );
      $this->visible = false;
      $this->object = null;
   }
}

class Path {
   public ?PathComponent $head = null;
   public ?PathComponent $tail = null;
   public bool $useCurrentModule = false;
}

class PathComponent {
   public ?PathComponent $next = null;
   public Position $pos;
   public string $name;
   public bool $shortcut = false;
}

class Attr {
   public Position $pos;
   public string $name;
   public array $args = [];
}

class TypeExpr extends Node {
   public Position $pos;
   public TypeVariant $root;

   public function __construct() {
      parent::__construct( NODE_TYPE_EXPR );
   }
}

class TypeVariant extends Node {
   /** @var Node[] */
   public array $options;

   public function __construct() {
      parent::__construct( NODE_TYPE_VARIANT );
      $this->options = [];
   }
}

class TypeTuple extends Node {
   /**
    * @var TypeArg[]
    */
   public array $args;
   public ?Structure $structure;

   public function __construct() {
      parent::__construct( NODE_TYPE_TUPLE );
      $this->args = [];
      $this->structure = null;
   }
}

class TypeArg {
   public string $name = '';
   public ?TypeExpr $expr = null;
   public ?Type $type = null;
}

class TypeRequest {
   /** @var TypeOption[] */
   public array $options = [];
   public ?Diagnostic $diag = null;
   public string $name = '';
}

const TYPE_OPTION_NAME = 0;
const TYPE_OPTION_INTEGER = 1;
const TYPE_OPTION_BOOL = 2;
const TYPE_OPTION_STR = 3;
const TYPE_OPTION_STRUCT = 4;
const TYPE_OPTION_TUPLE = 5;

class TypeOption {
   public Position $pos;
   public int $syntax;
   public ?string $name = null;
   /** @var TypeRequest[] */
   public array $args = [];
   public ?\Typing\FuncType $func;
   public ?Type $type = null;
   public ?Structure $structure = null;
   public ?Diagnostic $diag = null;
   public bool $important = false;
   public bool $borrowed = false;
   public bool $mutable = false;
   /** @var Refinement[] */
   public array $refinements = [];
   public mixed $value;

   public function findRefinement( string $target ): ?Refinement {
      foreach ( $this->refinements as $refinement ) {
         if ( $refinement->target === $target ) {
            return $refinement;
         }
      }
      return null;
   }
}

class Refinement {
   public Position $pos;
   public string|Value $target;
   public TypeRequest $refinedTypeRequest;
   public Type $type;
   public Value $result;
}

class Constant extends Node {
   public Name $name2;
   public Position $pos;
   public string $name;
   public ?TypeExpr $typeExpr;
   public ?Type $type;
   public ?Diagnostic $diag;
   public Expr $value;
   public ?Value $value2 = null;
   public bool $visible;
   public bool $resolved;

   public function __construct() {
      parent::__construct( NODE_CONSTANT );
      $this->typeExpr = null;
      $this->type = null;
      $this->diag = null;
      $this->visible = false;
      $this->resolved = false;
   }
}

class Enumeration extends Node {
   public Position $pos;
   public string $name;
   public array $params;
   /** @var Enumerator[] */
   public array $body;
   public int $index;
   public bool $visible;
   public bool $resolved;
   public bool $tagged;

   public function __construct() {
      parent::__construct( NODE_ENUM );
      $this->name = '';
      $this->params = [];
      $this->body = [];
      $this->visible = false;
      $this->resolved = false;
      $this->tagged = false;
   }

   public function findEnumerator( string $name ): ?\Enumerator {
      foreach ( $this->body as $enumerator ) {
         if ( $enumerator->name == $name ) {
            return $enumerator;
         }
      }
      return null;
   }
}

class Enumerator extends Node {
   public Position $pos;
   public string $name;
   public ?Expr $initializer;
   /** @var Param[] */
   public array $params;
   public Enumeration $enumeration;
   public Structure $structure;
   public ?TypeExpr $expectedType;
   public ?Type $type;
   public ?Value $result;
   public bool $visible;
   public mixed $value;
   public int $index;

   public function __construct() {
      parent::__construct( NODE_ENUMERATOR );
      $this->name = '';
      $this->initializer = null;
      $this->params = [];
      $this->expectedType = null;
      $this->type = null;
      $this->result = null;
      $this->visible = false;
   }
}

class Operators {
   public ?Func $eq = null;
   public ?Func $neq = null;
   public ?Func $minus = null;
   public ?Func $plus = null;
   public ?Func $preInc = null;
   public ?Func $preDec = null;
}

const OP_EQ = 0;
const OP_NEQ = 1;
const OP_LT = 2;
const OP_LTE = 3;
const OP_GT = 4;
const OP_GTE = 5;
const OP_ADD = 6;
const OP_SUB = 7;
const OP_MUL = 8;
const OP_DIV = 9;
const OP_MOD = 10;

const BUILTIN_STRUCTURE_NONE = 0;
const BUILTIN_STRUCTURE_VEC = 1;
const BUILTIN_STRUCTURE_STRUCT = 2;
const BUILTIN_STRUCTURE_INT = 3;
const BUILTIN_STRUCTURE_BOOL = 4;
const BUILTIN_STRUCTURE_STR = 5;
const BUILTIN_STRUCTURE_NEVER = 6;
const BUILTIN_STRUCTURE_ERR = 7;
const BUILTIN_STRUCTURE_PTR = 8;
const BUILTIN_STRUCTURE_CONST_PTR = 9;
const BUILTIN_STRUCTURE_TYPE = 10;
const BUILTIN_STRUCTURE_SELF = 11;
const BUILTIN_STRUCTURE_MACHINE = 12;
const BUILTIN_STRUCTURE_ANY = 13;

class Structure extends Node {
   public Position $pos;
   public string $name;
   public array $attrs;
   /**
    * @var StructureMember[]
    */
   public array $members;
   /** @var TypeParam[] */
   public array $params;
   public bool $visible;
   public bool $generic;
   public array $traitImpls;
   /** @var Implementation[] */
   public array $impls;
   public Operators $operators;
   public bool $placeholder;
   public bool $defined;
   public bool $resolved;
   public bool $trait;
   public bool $homogeneous;
   public int $size;
   public int $index;
   public ?Implementation $methods;
   public int $builtin;

   public function __construct() {
      parent::__construct( NODE_STRUCTURE );
      $this->name = '';
      $this->attrs = [];
      $this->members = [];
      $this->params = [];
      $this->visible = false;
      $this->generic = false;
      $this->traitImpls = [];
      $this->impls = [];
      $this->operators = new Operators();
      $this->placeholder = false;
      $this->defined = false;
      $this->resolved = false;
      $this->trait = false;
      $this->homogeneous = false;
      $this->size = 0;
      $this->index = 0;
      $this->methods = null;
      $this->builtin = BUILTIN_STRUCTURE_NONE;
   }

   public function getTraitImpl( string $traitName ): TraitImplementation {
      if ( ! array_key_exists( $traitName, $this->traitImpls ) ) {
         $this->traitImpls[ $traitName ] = new TraitImplementation();
      }
      return $this->traitImpls[ $traitName ];
   }

   public function findMember( string $name ): ?\StructureMember {
      foreach ( $this->members as $member ) {
         if ( $member->name == $name ) {
            return $member;
         }
      }
      return null;
   }
}

enum BuiltinStructMember {
   case NONE;
   case VALUE;
}

class StructureMember extends Node {
   public Position $pos;
   public string $name;
   public ?TypeExpr $typeExpr;
   public ?Type $type;
   public bool $visible;
   public bool $virtual;
   public bool $mutable;
   public ?Expr $defaultInitializer;
   public BuiltinStructMember $builtin;

   public function __construct() {
      parent::__construct( NODE_STRUCTURE_MEMBER );
      $this->typeExpr = null;
      $this->type = null;
      $this->visible = false;
      $this->virtual = false;
      $this->mutable = false;
      $this->defaultInitializer = null;
      $this->builtin = BuiltinStructMember::NONE;
   }
}

class TypeParam extends Node {
   public Position $pos;
   public string $name;
   public ?TypeExpr $expectedType;
   public ?Type $type;
   public bool $constant;
   public int $argPos;

   public function __construct() {
      parent::__construct( NODE_TYPE_PARAM );
      $this->expectedType = null;
      $this->type = null;
      $this->constant = false;
      $this->argPos = 0;
   }
}

class TraitObj extends Node {
   public string $name;
   public array $params;
   /** @var TraitMember[] */
   public array $members;

   public function __construct() {
      parent::__construct( NODE_TRAIT );
      $this->params = [];
      $this->members = [];
   }

   public function findFunc( string $name ): ?Func {
      foreach ( $this->members as $member ) {
         if ( $member->func->name == $name ) {
            return $member->func;
         }
      }
      return null;
   }
}

class TraitMember {
   public Func $func;
}

class Implementation {
   public Position $pos;
   /** @var Structure|null The trait that is implemented. */
   public ?Structure $trait;
   public string $traitName;
   public ?string $traitFuncName;
   public array $params;
   public Func $func;
   /** @var Func[] */
   public array $funcs;
   public array $funcTable;

   public function __construct() {
      $this->trait = null;
      $this->traitName = '';
      $this->traitFuncName = null;
      $this->params = [];
      $this->funcs = [];
      $this->funcTable = [];
   }

   public function findFunc( string $name ): ?Func {
      foreach ( $this->funcs as $func ) {
         if ( $func->name == $name ) {
            return $func;
         }
      }
      return null;
   }
}

class Param extends Node {
   public Position $pos;
   public string $name;
   public ?TypeExpr $expectedType;
   public ?TypeExpr $expectedTypeExpr;
   public ?Value $expectedValue;
   public ?Type $type;
   public bool $constant;
   public bool $rebindable;
   public ?Expr $defaultArg;
   public ?Value $value;
   public bool $isRefType;
   public \Codegen\CAst\CParam $cParam;

   public function __construct() {
      parent::__construct( NODE_PARAM );
      $this->name = '';
      $this->expectedType = null;
      $this->expectedTypeExpr = null;
      $this->expectedValue = null;
      $this->type = null;
      $this->constant = false;
      $this->rebindable = false;
      $this->defaultArg = null;
      $this->value = null;
      $this->isRefType = false;
   }
}

class Func extends Node {
   const BUILTIN_NONE = 0;
   const BUILTIN_DIAG = 1;
   const BUILTIN_BAIL = 2;
   const BUILTIN_ASSERT = 3;
   const BUILTIN_PRINT = 4;
   const BUILTIN_PRINTLN = 5;
   const BUILTIN_STRLEN = 6;
   const BUILTIN_DUMP = 7;
   const BUILTIN_STR_PTR = 8;
   const BUILTIN_INT_UNWRAP = 9;

   public Position $pos;
   public string $name;
   public ?BlockStmt $body;
   public array $attrs;
   public array $labels;
   /** @var TypeParam[] */
   public array $typeParams;
   /** @var Param[] */
   public array $params;
   /** @var Param[] */
   public array $returnParams;
   public int $builtin;
   public int $numCalls;
   public bool $virtual;
   public bool $evaluable;
   public bool $visible;
   public bool $internal;
   public bool $variadic;
   public bool $resolved;
   public bool $malformed;
   public bool $foreign;
   public ?TypeExpr $returnTypeExpr;
   public ?Type $returnType;
   public ?Implementation $impl;
   public ?Param $argsParam;

   public function __construct() {
      parent::__construct( NODE_FUNC );
      $this->name = '';
      $this->body = null;
      $this->attrs = [];
      $this->labels = [];
      $this->typeParams = [];
      $this->params = [];
      $this->returnParams = [];
      $this->builtin = self::BUILTIN_NONE;
      $this->numCalls = 0;
      $this->evaluable = false;
      $this->virtual = false;
      $this->visible = false;
      $this->internal = false;
      $this->variadic = false;
      $this->resolved = false;
      $this->malformed = false;
      $this->foreign = false;
      $this->impl = null;
      $this->argsParam = null;
      $this->returnTypeExpr = null;
      $this->returnType = null;
   }

   public function getParamPos( string $name ): ?int {
      foreach ( $this->params as $key => $param ) {
         if ( $param->name === $name ) {
            return $key;
         }
      }
      return null;
   }
}

class Initializer {
   public Expr $value;
   public array $children;
   public bool $braced;

   public function __construct() {
      $this->children = [];
      $this->braced = false;
   }
}

class Variable extends Node {
   public Position $pos;
   public string $name;
   public Type $type;
   public bool $initialized;
   public bool $compiletime;
   public int $index;
   public int $value;
   public bool $mutable;
   public ?Initializer $initializer;

   public function __construct() {
      parent::__construct( NODE_VAR );
      $this->type = new Type();
      $this->initialized = false;
      $this->compiletime = false;
      $this->index = 0;
      $this->value = 0;
      $this->mutable = false;
      $this->initializer = null;
   }
}

class Generic extends Node {
   public Position $pos;
   public string $name;
   public ?BlockStmt $body;
   public array $attrs;
   /** @var TypeParam[] */
   public array $params;
   public bool $visible;
   public bool $resolved;
   public ?Value $value;
   public ?Value $computedValue;

   public function __construct() {
      parent::__construct( NODE_GENERIC );
      $this->name = '';
      $this->body = null;
      $this->attrs = [];
      $this->params = [];
      $this->visible = false;
      $this->resolved = false;
      $this->value = null;
      $this->computedValue = null;
   }
}

class Let extends Node {
   public Name $name2;
   public Position $pos;
   public string $name;
   public ?TypeRequest $expectedType;
   public ?Expr $value;
   /** @var Param[] */
   public array $unpackedTuple;
   public ?Type $type;
   public bool $rebindable;

   public function __construct() {
      parent::__construct( NODE_LET );
      $this->expectedType = null;
      $this->value = null;
      $this->unpackedTuple = [];
      $this->type = null;
      $this->rebindable = false;
   }
}

class BlockStmt extends Node  {
   public Position $pos;
   /** @var ExprStmt[] */
   public array $stmts;
   public bool $evaluable;
   public bool $constant;
   public ?ExprStmt $returnValueExprStmt;

   public function __construct() {
      parent::__construct( NODE_BLOCKSTMT );
      $this->stmts = [];
      $this->evaluable = false;
      $this->constant = false;
      $this->returnValueExprStmt = null;
   }
}

class ErrStmt extends Node {
   public Diagnostic $err;
   public Node $faultyStmt;

   public function __construct() {
      parent::__construct( NODE_ERR_STMT );
   }
}

class IfStmt extends Node {
   /** @var IfItem[] */
   public array $ifs;
   public ?BlockStmt $elseBody;
   public bool $compiletime;
   public ?Diagnostic $err;
   public Typing\Type $type;

   public function __construct() {
      parent::__construct( NODE_IF );
      $this->ifs = [];
      $this->elseBody = null;
      $this->compiletime = false;
      $this->err = null;
   }
}

class IfItem {
   public Position $pos;
   /**
    * Codition.
    */
   public Expr $cond;
   public BlockStmt $body;
}

class SwitchCase {
   public array $values;
   public bool $isDefault;
   public BlockStmt $body;

   public function __construct() {
      $this->values = [];
      $this->isDefault = false;
   }
}

class SwitchStmt extends Node {
   /**
    * Codition.
    */
   public Expr $cond;
   public array $cases;

   public function __construct() {
      parent::__construct( NODE_SWITCH );
      $this->cases = [];
   }
}

class MatchExpr extends Node {
   public Expr $cond;
   /** @var MatchArm[] */
   public array $arms;

   public function __construct() {
      parent::__construct( NODE_MATCH );
      $this->arms = [];
   }
}

class MatchArm {
   public array $patterns;
   public BlockStmt $body;

   public function __construct() {
      $this->patterns = [];
   }
}

class WhileStmt extends Node {
   /**
    * Codition.
    */
   public Expr $cond;
   public BlockStmt $body;
   public ?BlockStmt $endfully;

   public function __construct() {
      parent::__construct( NODE_WHILE );
   }
}

class ForLoop extends Node {
   /**
    * Codition.
    */
   public ?ForItem $item;
   public Expr $collection;
   public BlockStmt $body;
   public ?BlockStmt $endfully;

   public function __construct() {
      parent::__construct( NODE_FOR );
      $this->item = null;
   }
}

class ForItem extends Node {
   public string $name;
   public Type $type;

   public function __construct() {
      parent::__construct( NODE_FOR_ITEM );
   }
}

const JUMP_BREAK = 0;
const JUMP_CONTINUE = 1;

class Jump extends Node {
   public Position $pos;
   public int $type;

   public function __construct() {
      parent::__construct( NODE_JUMP );
   }
}

class ReturnStmt extends Node {
   public Position $pos;
   /** @var Expr[] */
   public array $values;
   public ?Expr $value;

   public function __construct() {
      parent::__construct( NODE_RETURN_STMT );
      $this->values = [];
      $this->value = null;
   }
}

class DropExpr extends Node {
   public Position $pos;
   /** @var Arg[] */
   public array $values;

   public function __construct() {
      parent::__construct( NODE_DROP );
      $this->values = [];
   }
}

class ExprStmt extends Node {
   public Expr $expr;
   public ?string $binding;
   public ?Type $bindingType;
   public ?Value $result;
   public bool $yield;
   /** @var MatchArm[] */
   public array $arms;

   public function __construct() {
      parent::__construct( NODE_EXPR_STMT );
      $this->binding = null;
      $this->bindingType = null;
      $this->result = null;
      $this->yield = false;
      $this->arms = [];
   }
}

class Expr extends Node {
   public Position $pos;
   public ?Node $root;
   public ?Typing\Type $type;
   public bool $constant;
   public bool $compound;
   public bool $virtual;
   public string $tag;
   public mixed $value;

   public function __construct() {
      parent::__construct( NODE_EXPR );
      $this->root = null;
      $this->type = new Typing\Type();
      $this->constant = false;
      $this->compound = false;
      $this->virtual = false;
      $this->tag = '';
      $this->value = 0;
   }
}

class Arg {
   public string $name = '';
   public ?TypeExpr $typeExpr = null;
   public Expr $expr;
}

class Assignment extends Node {
   public Position $pos;
   public Node $lside;
   public Node $rside;

   public function __construct() {
      parent::__construct( NODE_ASSIGNMENT );
   }
}

enum Implementer {
   case NONE;
   case INT;
   case STR;
   case PTR;
}

class Binary extends Node {
   public const OP_EQ = 0;
   public const OP_NEQ = 1;
   public const OP_LT = 2;
   public const OP_LTE = 3;
   public const OP_GT = 4;
   public const OP_GTE = 5;
   public const OP_ADD = 6;
   public const OP_SUB = 7;
   public const OP_MUL = 8;
   public const OP_DIV = 9;
   public const OP_MOD = 10;

   public Position $pos;
   public int $op;
   public Node $lside;
   public Node $rside;
   public Type $type;
   public Implementer $implementer;

   public function __construct() {
      parent::__construct( NODE_BINARY );
      $this->implementer = Implementer::NONE;
   }

   public function presentOperator(): string {
      switch ( $this->op ) {
      case self::OP_EQ: return '==';
      case self::OP_NEQ: return '!=';
      case self::OP_LT: return '<';
      case self::OP_LTE: return '<=';
      case self::OP_GT: return '>';
      case self::OP_GTE: return '>=';
      case self::OP_ADD: return '+';
      case self::OP_SUB: return '-';
      case self::OP_MUL: return '*';
      case self::OP_DIV: return '/';
      case self::OP_MOD: return '%';
      default:
         throw new \Exception();
      }
   }
}

class Logical extends Node {
   public const OPERATOR_AND = 0;
   public const OPERATOR_OR = 1;

   public Position $pos;
   public int $operator;
   public Node $lside;
   public Node $rside;

   public function __construct() {
      parent::__construct( NODE_LOGICAL );
   }
}

const CALL_NONE = 0;
const CALL_FUNC = 1;
const CALL_STRUCTURE = 2;
const CALL_STRUCTURE_VALUE = 3;
const CALL_ENUM = 4;
const CALL_TRAIT = 5;

class Call extends Node {
   public Position $pos;
   public int $type;
   public Node $operand;
   public Func $func;
   /** @var Arg[] */
   public array $args;
   public int $result;
   public bool $method;
   public ?Structure $structure;
   public ?Enumerator $enumerator;

   public function __construct() {
      parent::__construct( NODE_CALL );
      $this->type = CALL_NONE;
      $this->args = [];
      $this->result = 0;
      $this->method = false;
      $this->structure = null;
      $this->enumerator = null;
   }
}

class TypeCall extends Node {
   public Position $pos;
   public Node $operand;
   /** @var TypeArg[] */
   public array $args = [];
   public bool $generic;

   public function __construct() {
      parent::__construct( NODE_TYPE_CALL );
      $this->args = [];
      $this->generic = false;
   }
}

const ACCESS_NONE = 0;
const ACCESS_MEMBER = 1;
const ACCESS_TRAIT_MEMBER = 2;
const ACCESS_METHOD = 3;
const ACCESS_ERR = 4;
const ACCESS_STRUCTURE_SIZE = 5;
const ACCESS_STRUCTURE_NAME = 6;

class Access extends Node {
   public Position $pos;
   public Node $lside;
   public string $memberName;
   public bool $isBool;
   public int $type;
   public Structure $structure;
   public ?Diagnostic $err;
   public ?Func $method;

   public function __construct() {
      parent::__construct( NODE_ACCESS );
      $this->isBool = false;
      $this->type = ACCESS_NONE;
      $this->err = null;
      $this->method = null;
   }
}

class Subscript extends Node {
   public Position $pos;
   public Node $operand;
   public ?Node $value;
   public array $indexes;
   public array $dims;
   public bool $isPointer;

   public function __construct() {
      parent::__construct( NODE_SUBSCRIPT );
      $this->value = null;
      $this->indexes = [];
      $this->dims = [];
      $this->isPointer = false;
   }
}

class Propagation extends Node {
   public Position $pos;
   public Node $operand;

   public function __construct() {
      parent::__construct( NODE_PROPAGATION );
   }
}

class NullPointer extends Node {
   public function __construct() {
      parent::__construct( NODE_NULL_POINTER );
   }
}

class Sizeof extends Node {
   public Position $pos;
   public Expr $expr;
   public int $size;

   public function __construct() {
      parent::__construct( NODE_SIZEOF );
   }
}

class NameUsage extends Node {
   public Position $pos;
   public Position $moduleNamePos;
   public ?Module $module;
   public string $name;
   public string $moduleName;
   /** @var Arg[] */
   public array $args;
   public bool $compiletime;
   public bool $mutable;
   public bool $argsListSpecified;
   public Value $value;
   public ?Node $object;

   public function __construct() {
      parent::__construct( NODE_NAME_USAGE );
      $this->moduleName = '';
      $this->module = null;
      $this->args = [];
      $this->compiletime = false;
      $this->mutable = false;
      $this->argsListSpecified = false;
      $this->object = null;
   }
}

class IntegerLiteral extends Node {
   public Position $pos;
   public int $value;

   public function __construct() {
      parent::__construct( NODE_INTEGER_LITERAL );
   }
}

class StringLiteral extends Node {
   public string $value;
   public int $index;

   public function __construct() {
      parent::__construct( NODE_STRING_LITERAL );
   }
}

class BoolLiteral extends Node {
   public int $value;

   public function __construct() {
      parent::__construct( NODE_BOOL_LITERAL );
      $this->value = 0;
   }
}

class TypeLiteral extends Node {
   public Expr $typeExpr;
   public Type $type;

   public function __construct() {
      parent::__construct( NODE_TYPE_LITERAL );
   }
}

class TraitImplementation {
   private array $memberImpls;

   public function __construct() {
      $this->memberImpls = [];
   }

   public function getMemberImpl( string $name ): ?Func {
      if ( array_key_exists( $name, $this->memberImpls ) ) {
         return $this->memberImpls[ $name ];
      }
      return null;
   }

   public function setMemberImpl( string $name, Func $func ): void {
      $this->memberImpls[ $name ] = $func;
   }
}

class TypeAlias extends Node {
   public string $name;
   public Type $type;
   public Position $pos;
   public bool $visible;

   public function __construct() {
      parent::__construct( NODE_TYPE_ALIAS );
      $this->visible = false;
   }
}

class Module {
   public string $path;
   /**
    * @var Node[]
    */
   public array $items;
   /**
    * @var Structure[]
    */
   public array $tuples;
   public array $funcs;
   /** @var SubModule[] */
   public array $subModules;
   /** @var Import[] */
   public array $imports;
   /** @var Module[] */
   public array $importedModules;
   /** @var Module[] */
   public array $visibleImportedModules;
   public array $formats;
   public array $stringTable;
   /** @var Module[] */
   public array $prefixes;
   public ScopeFloor $scope;

   public bool $checkedPrototypes;
   public bool $checked;
   public bool $deferErrs;
   public bool $package;
   public bool $implicit;

   public function __construct() {
      $this->items = [];
      $this->tuples = [];
      $this->funcs = [];
      $this->subModules = [];
      $this->imports = [];
      $this->importedModules = [];
      $this->visibleImportedModules = [];
      $this->formats = [];
      $this->stringTable = [];
      $this->prefixes = [];
      $this->scope = new ScopeFloor();
      $this->checkedPrototypes = false;
      $this->checked = false;
      $this->deferErrs = false;
      $this->package = false;
      $this->implicit = false;
   }
}

class SubModule {
   public string $name;
   public bool $visible = false;
   public ?Module $module = null;
}

const UOP_MINUS = 0;
const UOP_PLUS = 1;
const UOP_PRE_INC = 2;
const UOP_PRE_DEC = 3;
const UOP_ADDR_OF = 4;
const UOP_IMPORTANT = 5;

class Unary extends Node {
   public Position $pos;
   public Node $operand;
   public int $op;

   public function __construct() {
      parent::__construct( NODE_UNARY );
   }

   public function presentOperator(): string {
      switch ( $this->op ) {
      case UOP_MINUS: return '-';
      case UOP_PLUS: return '+';
      case UOP_ADDR_OF: return '&';
      default:
         throw new Exception();
      }
   }
}

class Borrow extends Node {
   public Node $operand;
   public bool $mutable;

   public function __construct() {
      parent::__construct( NODE_BORROW );
      $this->mutable = false;
   }
}

class LogicalNot extends Node {
   public Position $pos;
   public Node $operand;

   public function __construct() {
      parent::__construct( NODE_LOGICAL_NOT );
   }
}

class Tuple extends Node {
   /**
    * @var Arg[]
    */
   public array $args;
   public ?Structure $structure;

   public function __construct() {
      parent::__construct( NODE_TUPLE );
      $this->args = [];
      $this->structure = null;
   }
}

class Like extends Node {
   public Node $operand;
   public Pattern $pattern;
   public bool $not;

   public function __construct() {
      parent::__construct( NODE_LIKE );
      $this->not = false;
   }
}

const PATTERN_NAME = 0;
const PATTERN_CALL = 1;
const PATTERN_INTEGER_LITERAL = 2;
const PATTERN_BOOL_LITERAL = 3;

const MATCH_ENUM_TAG = 0;
const MATCH_ENUMERATOR = 1;

class Pattern {
   public Position $pos;
   public int $type;
   public string $name;
   public string $param = '';
   public Tuple $namedArgs;
   public int $match;
   /**
    * @var Pattern[]
    */
   public array $args = [];
   public ?Enumerator $enumerator = null;
   public ?IntegerLiteral $integerLiteral = null;
   public ?BoolLiteral $boolLiteral = null;
}

class Seen extends Node {
   public function __construct() {
      parent::__construct( NODE_SEEN );
   }
}
