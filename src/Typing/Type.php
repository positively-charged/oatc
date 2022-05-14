<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;
use Param;

const NATIVE_TYPE_I8 = 0;
const NATIVE_TYPE_I16 = 1;
const NATIVE_TYPE_I32 = 2;
const NATIVE_TYPE_I64 = 3;
const NATIVE_TYPE_U8 = 4;
const NATIVE_TYPE_U16 = 5;
const NATIVE_TYPE_U32 = 6;
const NATIVE_TYPE_U64 = 7;
const NATIVE_TYPE_CHAR = 8;
const NATIVE_TYPE_SCHAR = 9;
const NATIVE_TYPE_UCHAR = 10;

$value = 0;
define( 'TYPESPEC_UNKNOWN', $value++ );
define( 'TYPESPEC_VOID', $value++ );
define( 'TYPESPEC_STRUCT', $value++ );
define( 'TYPESPEC_ENUM', $value++ );
define( 'TYPESPEC_TRAIT', $value++ );
define( 'TYPESPEC_TYPE', $value++ );
define( 'TYPESPEC_STRUCT_TYPE', $value++ );
define( 'TYPESPEC_STRUCT_INFO', $value++ );
define( 'TYPESPEC_ENUM_TYPE', $value++ );
define( 'TYPESPEC_ERR', $value++ );
define( 'TYPESPEC_VALUE', $value++ );
define( 'TYPESPEC_UNRESOLVED', $value++ );
define( 'TYPESPEC_GENERIC', $value++ );
define( 'TYPESPEC_INT64', $value++ );
define( 'TYPESPEC_BINDING', $value++ );
unset( $value );

class Ptr {
   public Type $type;
}

class FuncType {
   public array $params;
   public ?Type $returnType;

   public function __construct() {
      $this->params = [];
      $this->returnType = null;
   }
}

class Type {
   public int $spec;
   public string $name;
   /** @var Param[] */
   public array $params;
   /** @var Type[] */
   public array $args;
   public ?FuncType $func;
   public ?\Enumeration $enumeration;
   public ?\Structure $structure;
   public ?\TraitObj $trait;
   public bool $placeholder;
   public bool $unique;
   public bool $borrowed;
   public bool $constant;
   public bool $mutable;
   public bool $unchecked;
   public bool $important;
   public array $substitutions;
   /** @var \Refinement[] */
   public array $refinements;
   public int $min;
   public int $max;
   public int $representation;
   public ?\TypeParam $typeParam;
   public ?\Binding $binding;

   public ?Ptr $ptr;
   public mixed $value;

   public function __construct() {
      $this->spec = TYPESPEC_VOID;
      $this->enumeration = null;
      $this->structure = null;
      $this->trait = null;
      $this->params = [];
      $this->args = [];
      $this->placeholder = false;
      $this->unique = false;
      $this->borrowed = false;
      $this->constant = false;
      $this->mutable = false;
      $this->unchecked = false;
      $this->important = false;
      $this->substitutions = [];
      $this->refinements = [];
      $this->ptr = null;
      $this->value = null;
      $this->typeParam = null;
      $this->binding = null;
      $this->min = 0;
      $this->max = 0;
      $this->representation = NATIVE_TYPE_I64;
   }
}
