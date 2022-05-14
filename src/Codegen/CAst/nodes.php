<?php

declare( strict_types = 1 );

namespace Codegen\CAst;

const CNODE_EXPR = 0;
const CNODE_COMPOUND = 1;
const CNODE_IF = 2;
const CNODE_INTEGER_LITERAL = 10;
const CNODE_BINARY = 11;
const CNODE_CALL = 12;
const CNODE_STRING_LITERAL = 13;
const CNODE_NAME_USAGE = 14;
const CNODE_STRUCT = 15;
const CNODE_ALLOC = 16;
const CNODE_DEREF = 17;
const CNODE_ASSERT = 18;
const CNODE_UNARY = 19;
const CNODE_PAREN = 20;
const CNODE_RETURN_STMT = 21;
const CNODE_WHILE = 22;
const CNODE_ASSIGNMENT = 23;
const CNODE_SWITCH = 24;
const CNODE_CASE = 25;
const CNODE_DEFAULT_CASE = 26;
const CNODE_BREAK = 27;
const CNODE_POINTER_DEREF = 28;
const CNODE_CAST = 29;
const CNODE_NULL_POINTER = 30;
const CNODE_VAR = 31;
const CNODE_ACCESS = 32;
const CNODE_CLEANUP_STMT = 33;
const CNODE_FREE_STMT = 34;
const CNODE_REPLACE_REF = 35;
const CNODE_ENUM_CLEANUP_STMT = 36;
const CNODE_ERR = 37;
const CNODE_ERR_STMT = 38;
const CNODE_EMPTY = 39;
const CNODE_INTEGER_LITERAL_ASSIGNMENT = 40;
const CNODE_STRING_LITERAL_ASSIGNMENT = 41;
const CNODE_SHARE = 42;
const CNODE_SUBSCRIPT = 43;
const CNODE_UNION_VALUE = 44;
const CNODE_TAG_MATCH = 45;
const CNODE_UNION_ACCESS = 46;

const SPEC_VOID = 0;
const SPEC_BOOL = 1;
const SPEC_CHAR = 2;
const SPEC_INT8 = 3;
const SPEC_INT16 = 4;
const SPEC_INT32 = 5;
const SPEC_INT64 = 6;
const SPEC_UINT8 = 7;
const SPEC_UINT16 = 8;
const SPEC_UINT32 = 9;
const SPEC_UINT64 = 10;
const SPEC_STRUCT = 11;
const SPEC_NESTED_STRUCT = 12;
const SPEC_STRUCTPTR = 13;
const SPEC_STR = 14;

class CType {
   public function __construct(
      public bool $const = false,
      public int $spec = SPEC_VOID,
      public ?CStruct $struct = null,
      public array $pointers = [],
      public ?CParamList $params = null,
      public ?CType $parens = null,
   ) {}

   public function deref(): self {
      if ( count( $this->pointers ) > 0 ) {
         $type = clone $this;
         array_pop( $type->pointers );
         return $type;
      }
      return $this;
   }
}

class CTranslationUnit {
   public function __construct(
      public array $headers = [],
      public array $userHeaders = [],
      public StructSet $structs = new StructSet(),
      public array $vars = [],
      public array $funcs = [],
      public array $symbols = [],
      public array $typeSymbols = [],
      /** @var string[] */
      public array $strings = [],
   ) {}
}

class CFunc {
   public string $name;
   public bool $static;
   public bool $variadic;
   public CType $returnType;
   /** @var CParam[] */
   public array $params;
   public ?CParam $selfParam;
   public ?CCompoundStmt $body;

   public function __construct() {
      $this->static = false;
      $this->variadic = false;
      $this->returnType = new CType();
      $this->params = [];
      $this->selfParam = null;
      $this->body = null;
   }
}

class CNode {
   public int $nodeType;

   public function __construct( int $type ) {
      $this->nodeType = $type;
   }
}

class CCompoundStmt extends CNode {
   /** @var CVar[] */
   public array $vars;
   public Group $items;
   public array $cleanupItems;
   public ?CCompoundStmt $cleanup;
   public ?CVar $returnValue;
   public array $allocs;
   public bool $groupOnly;

   public function __construct() {
      parent::__construct( CNODE_COMPOUND );
      $this->vars = [];
      $this->cleanupItems = [];
      $this->cleanup = null;
      $this->returnValue = null;
      $this->allocs = [];
      $this->groupOnly = false;
   }
}

class CIfStmt extends CNode {
   public CVar $cond;
   public Group $body;
   public ?Group $elseBody;

   public function __construct() {
      parent::__construct( CNODE_IF );
      $this->elseBody = null;
   }
}

class CAssert extends CNode {
   public CExpr $expr;
   public string $file;
   public int $line;
   public int $column;

   public function __construct() {
      parent::__construct( CNODE_ASSERT );
   }
}

class CExprStmt extends CNode {
   public CExpr $expr;

   public function __construct() {
      parent::__construct( CNODE_EXPR );
   }
}

const CBINARY_EQ = 0;
const CBINARY_NEQ = 1;
const CBINARY_LT = 2;
const CBINARY_LTE = 3;
const CBINARY_GT = 4;
const CBINARY_GTE = 5;
const CBINARY_ADD = 6;
const CBINARY_SUB = 7;
const CBINARY_MUL = 8;
const CBINARY_DIV = 9;
const CBINARY_MOD = 10;
const CBINARY_LOGAND = 11;
const CBINARY_LOGOR = 12;

class CBinary extends CNode {
   public CVar $result;
   public CVar $lside;
   public CVar $rside;
   public int $op;

   public function __construct() {
      parent::__construct( CNODE_BINARY );
   }
}

class CIntegerLiteral extends CNode {
   public int $value;

   public function __construct() {
      parent::__construct( CNODE_INTEGER_LITERAL );
   }
}

class CIntegerLiteralAssignment extends CNode {
   public Cvar $var;
   public int $value;

   public function __construct() {
      parent::__construct( CNODE_INTEGER_LITERAL_ASSIGNMENT );
   }
}

class CStringLiteral extends CNode {
   public string $value;

   public function __construct() {
      parent::__construct( CNODE_STRING_LITERAL );
   }
}

class CStringLiteralAssignment extends CNode {
   public Cvar $var;
   public string $value;

   public function __construct() {
      parent::__construct( CNODE_STRING_LITERAL_ASSIGNMENT );
   }
}

const CCALL_PRINTF = 0;
const CCALL_USER = 1;
const CCALL_OPERAND = 2;

class CCall extends CNode {
   public int $func;
   public CNode $operand;
   /** @var CArg[] */
   public array $args;
   public ?CVar $returnValue;

   public function __construct() {
      parent::__construct( CNODE_CALL );
      $this->args = [];
      $this->returnValue = null;
   }
}

class CArg {
   public CVar $var;
   public bool $addrof = false;
}

class CDeclarator {
   public array $pointers = [];
   public array $dims = [];
   public ?CParamList $params = null;
   public string $name;
   public ?CDeclarator $parens = null;
}

class CPointer {
   public bool $constant = false;
}

class CParamList {
   public array $params;
}

class CParam {
   public int $index;
   public string $name = '';
   public CType $type;
   public CVar $var;
}


class CNameUsage extends CNode {
   public $object;
   public string $name;
   public bool $isParam = false;

   public function __construct() {
      parent::__construct( CNODE_NAME_USAGE );
   }
}

class CStructMember {
   public string $name = '';
   public int $index;
   public CType $type;
   public ?CDeclarator $declarator = null;
   public array $dims = [];
   public bool $traitMember = false;
   public ?CFunc $traitMethod = null;
}

class CStruct extends CNode {
   public int $index = -1;
   public string $name;
   public string $originalName;
   /** @var CStructMember[] */
   public array $members;
   public bool $union;
   public bool $homogeneous;
   public bool $trait;
   public bool $refCounted;
   public ?CFunc $cleanupFunc;

   public function __construct() {
      parent::__construct( CNODE_STRUCT );
      $this->name = '';
      $this->originalName = '';
      $this->members = [];
      $this->union = false;
      $this->homogeneous = false;
      $this->trait = false;
      $this->refCounted = false;
      $this->cleanupFunc = null;
   }
}

class CExpr {
   public ?CVar $result;
   public ?CNode $root;

   public function __construct() {
      $this->result = null;
      $this->root = null;
   }
}

class Result {
   public bool $method = false;
   public ?\Structure $structure = null;
   public ?\StructureMember $member = null;
   public ?\Enumerator $enumerator = null;
   public ?\TraitObj $trait = null;
   public ?CVar $var = null;
   public string $name = '';
}

class CAllocInitializer {
   public string $member = '';
   public string $comment = '';
   public int $memberInt;
   public array $children;
   public CVar $value;
   public bool $incRefCount = false;
}

class CAlloc extends CNode {
   public CVar $var;
   public CStruct $struct;
   public string $name;
   /** @var CAllocInitializer[] */
   public array $initializers;
   public bool $stack = false;
   public bool $skipMalloc = false;
   public bool $externalRefs = false;
   public int $numLabelsAttached;
   public ?CExpr $initializer = null;
   public bool $borrowed = false;

   public function __construct() {
      parent::__construct( CNODE_ALLOC );
      $this->initializers = [];
      $this->numLabelsAttached = 0;
   }
}

class CDeref extends CNode {
   public CVar $operand;
   /** @var int Index of the struct member being accessed. */
   public int $member;
   public bool $isBool;
   public bool $subscript;
   public CVar $result;

   public function __construct() {
      parent::__construct( CNODE_DEREF );
      $this->isBool = false;
      $this->subscript = false;
   }
}

class CPointerDeref extends CNode {
   public CVar|CParam $operand;
   public CVar $result;
   public ?CVar $index;
   public ?CVar $value;

   public function __construct() {
      parent::__construct( CNODE_POINTER_DEREF );
      $this->index = null;
      $this->value = null;
   }
}

const CUOP_MINUS = 0;
const CUOP_NOT = 1;
const CUOP_ADDROF = 2;
const CUOP_PRE_INC = 3;
const CUOP_PRE_DEC = 4;

class CUnary extends CNode {
   public CVar $operand;
   public ?CVar $result;
   public int $op;

   public function __construct() {
      parent::__construct( CNODE_UNARY );
      $this->result = null;
   }
}

class CParen extends CNode {
   public CExpr $expr;

   public function __construct() {
      parent::__construct( CNODE_PAREN );
   }
}

class CReturnStmt extends CNode {
   public ?CVar $value;

   public function __construct() {
      parent::__construct( CNODE_RETURN_STMT );
      $this->value = null;
   }
}

class CWhileStmt extends CNode {
   public CVar $cond;
   public Group $condGroup;
   public Group $body;

   public function __construct() {
      parent::__construct( CNODE_WHILE );
   }
}

class CAssignment extends CNode {
   public CVar $lside;
   public CVar $rside;
   public bool $deref;

   public function __construct() {
      parent::__construct( CNODE_ASSIGNMENT );
      $this->deref = false;
   }
}

class CSwitchStmt extends CNode {
   public CExpr $cond;
   public CCompoundStmt $body;

   public function __construct() {
      parent::__construct( CNODE_SWITCH );
      $this->body = new CCompoundStmt();
   }
}

class CCase extends CNode {
   public int $value;

   public function __construct() {
      parent::__construct( CNODE_CASE );
   }
}

class CDefaultCase extends CNode {
   public function __construct() {
      parent::__construct( CNODE_DEFAULT_CASE );
   }
}

class CBreak extends CNode {
   public function __construct() {
      parent::__construct( CNODE_BREAK );
   }
}

class CCast extends CNode {
   public int $spec;
   public array $pointers;
   public ?CStruct $struct;
   public CExpr $value;

   public function __construct() {
      parent::__construct( CNODE_CAST );
      $this->pointers = [];
      $this->struct = null;
   }
}

class CNullPointer extends CNode {
   public function __construct() {
      parent::__construct( CNODE_NULL_POINTER );
   }
}

class CVar extends CNode {
   public int $spec;
   public array $pointers;
   public array $dims;
   public ?CStruct $struct;
   public CExpr $value;
   public string $name;
   public string $label;
   public bool $static;
   public bool $const;
   public bool $allocated;
   public bool $addrof;
   public ?CBracedInitializer $initializer;
   public CType $type;
   public int $refs;

   public function __construct() {
      parent::__construct( CNODE_VAR );
      $this->pointers = [];
      $this->dims = [];
      $this->struct = null;
      $this->static = false;
      $this->const = false;
      $this->initializer = null;
      $this->type = new CType();
      $this->label = '';
      $this->allocated = false;
      $this->addrof = false;
      $this->refs = 0;
   }
}

class CBracedInitializer {
   public array $children = [];
}

class CAccess extends CNode {
   public CNode $object;
   public int $member;

   public function __construct() {
      parent::__construct( CNODE_ACCESS );
   }
}

class CCleanupStmt extends CNode {
   public string $object;
   public ?CAlloc $alloc;
   public CStruct $struct;

   public function __construct() {
      parent::__construct( CNODE_CLEANUP_STMT );
      $this->alloc = null;
   }
}

class CFreeStmt extends CNode {
   public CVar $var;

   public function __construct() {
      parent::__construct( CNODE_FREE_STMT );
   }
}

class CReplaceRef extends CNode {
   public CNode $member;
   public CNode $replacement;
   public CAlloc $alloc;

   public function __construct() {
      parent::__construct( CNODE_REPLACE_REF );
   }
}

class CEnumCleanupStmt extends CNode {
   public CParam $param;
   public array $cases;

   public function __construct() {
      parent::__construct( CNODE_ENUM_CLEANUP_STMT );
      $this->cases = [];
   }
}

class CEnumCleanupCase {
   public string $field;
   public int $tag;
   public array $params = [];
}

class CErr extends CNode {
   public string $message;

   public function __construct() {
      parent::__construct( CNODE_ERR );
   }
}

class CErrStmt extends CNode {
   public string $message;

   public function __construct() {
      parent::__construct( CNODE_ERR_STMT );
   }
}

class CEmpty extends CNode {
   public function __construct() {
      parent::__construct( CNODE_EMPTY );
   }
}

class CShare extends CNode {
   public CVar $var;

   public function __construct() {
      parent::__construct( CNODE_SHARE );
   }
}

class CSubscript extends CNode {
   public CVar $base;
   public CVar $index;
   public CVar $result;

   public function __construct() {
      parent::__construct( CNODE_SUBSCRIPT );
   }
}

class CUnionValue extends CNode {
   public CStruct $struct;
   public int $member;
   public CVar $value;
   public CVar $result;

   public function __construct() {
      parent::__construct( CNODE_UNION_VALUE );
   }
}

class CTagMatch extends CNode {
   public CVar $operand;
   public int $member;
   public CVar $result;

   public function __construct() {
      parent::__construct( CNODE_TAG_MATCH );
   }
}

class CUnionAccess extends CNode {
   public CVar $operand;
   public CVar $result;
   public int $member;

   public function __construct() {
      parent::__construct( CNODE_UNION_ACCESS );
   }
}
